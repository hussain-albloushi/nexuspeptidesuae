<?php

namespace App\Http\Controllers;

use App\Models\InventoryTransaction;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    private const STATUSES = ['in_stock', 'low_stock', 'out_of_stock'];

    private const TYPES = [
        'restock', 'adjustment', 'correction', 'damage',
        'return', 'sale', 'transfer', 'other',
    ];

    public function index(Request $request)
    {
        $request->validate([
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $products = Product::with('category')
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%' . $request->string('q')->trim() . '%';
                $query->where(function ($q) use ($term) {
                    $q->where('name', 'like', $term)
                        ->orWhere('sku', 'like', $term);
                });
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                match ($request->status) {
                    'out_of_stock' => $query->where('stock_quantity', '<=', 0),
                    'low_stock' => $query->where('stock_quantity', '>', 0)
                        ->whereColumn('stock_quantity', '<=', 'low_stock_threshold'),
                    'in_stock' => $query->whereColumn('stock_quantity', '>', 'low_stock_threshold'),
                };
            })
            ->orderByRaw('CASE WHEN stock_quantity <= 0 THEN 0 WHEN stock_quantity <= low_stock_threshold THEN 1 ELSE 2 END')
            ->orderBy('name')
            ->get()
            ->map(function (Product $product) {
                $status = $this->statusFor((int) $product->stock_quantity, (int) $product->low_stock_threshold);

                return array_merge($product->toArray(), [
                    'computed_status' => $status,
                    'inventory_value' => round(((float) ($product->cost_price ?? 0)) * (int) $product->stock_quantity, 2),
                ]);
            });

        return response()->json([
            'summary' => $this->summary(),
            'products' => $products,
        ]);
    }

    public function adjust(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'mode' => ['required', Rule::in(['increase', 'decrease', 'set'])],
            'quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'type' => ['required', Rule::in(self::TYPES)],
            'reason' => ['required', 'string', 'max:1000'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ]);

        $result = DB::transaction(function () use ($data) {
            $product = Product::whereKey($data['product_id'])->lockForUpdate()->firstOrFail();

            $before = (int) $product->stock_quantity;
            $after = match ($data['mode']) {
                'increase' => $before + (int) $data['quantity'],
                'decrease' => $before - (int) $data['quantity'],
                'set' => (int) $data['quantity'],
            };

            if ($after < 0) {
                abort(422, 'Insufficient stock: cannot remove more units than are on hand.');
            }

            $threshold = array_key_exists('low_stock_threshold', $data) && $data['low_stock_threshold'] !== null
                ? (int) $data['low_stock_threshold']
                : (int) $product->low_stock_threshold;

            $product->update([
                'stock_quantity' => $after,
                'stock_status' => $this->statusFor($after, $threshold),
                'low_stock_threshold' => $threshold,
            ]);

            $transaction = InventoryTransaction::create([
                'product_id' => $product->id,
                'quantity_before' => $before,
                'quantity_after' => $after,
                'type' => $data['type'],
                'reason' => $data['reason'],
            ]);

            return [
                'product' => $product->fresh('category'),
                'transaction' => $transaction,
                'summary' => $this->summary(),
            ];
        });

        return response()->json($result + ['message' => 'Stock adjusted successfully.']);
    }

    public function history(Request $request)
    {
        $request->validate([
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'type' => ['nullable', Rule::in(self::TYPES)],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $history = InventoryTransaction::with('product:id,name,sku')
            ->when($request->filled('product_id'), fn ($query) => $query->where('product_id', $request->integer('product_id')))
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->type))
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%' . $request->string('q')->trim() . '%';
                $query->where(function ($q) use ($term) {
                    $q->where('reason', 'like', $term)
                        ->orWhereHas('product', function ($p) use ($term) {
                            $p->where('name', 'like', $term)->orWhere('sku', 'like', $term);
                        });
                });
            })
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return response()->json($history);
    }

    public function updateThreshold(Request $request, Product $product)
    {
        $data = $request->validate([
            'low_stock_threshold' => ['required', 'integer', 'min:0', 'max:1000000'],
        ]);

        $product->update([
            'low_stock_threshold' => (int) $data['low_stock_threshold'],
            'stock_status' => $this->statusFor((int) $product->stock_quantity, (int) $data['low_stock_threshold']),
        ]);

        return response()->json([
            'message' => 'Low-stock threshold updated.',
            'product' => $product->fresh('category'),
            'summary' => $this->summary(),
        ]);
    }

    private function statusFor(int $quantity, int $threshold): string
    {
        if ($quantity <= 0) {
            return 'out_of_stock';
        }

        return $quantity <= $threshold ? 'low_stock' : 'in_stock';
    }

    private function summary(): array
    {
        $inventoryValue = Product::sum(DB::raw('stock_quantity * COALESCE(cost_price, 0)'));

        return [
            'total_products' => Product::count(),
            'total_units' => (int) Product::sum('stock_quantity'),
            'low_stock' => Product::where('stock_quantity', '>', 0)
                ->whereColumn('stock_quantity', '<=', 'low_stock_threshold')->count(),
            'out_of_stock' => Product::where('stock_quantity', '<=', 0)->count(),
            'inventory_value' => round((float) $inventoryValue, 2),
        ];
    }
}
