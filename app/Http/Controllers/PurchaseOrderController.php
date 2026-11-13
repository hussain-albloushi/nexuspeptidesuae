<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\SupplierPurchaseOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PurchaseOrderController extends Controller
{
    private const OPEN_STATUSES = ['draft', 'approved'];

    public function index(Request $r)
    {
        return SupplierPurchaseOrder::with('supplier')->withCount('items')->latest()->get();
    }

    public function show($id)
    {
        return SupplierPurchaseOrder::with(['supplier', 'items', 'expense'])->findOrFail($id);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $order = DB::transaction(function () use ($data): SupplierPurchaseOrder {
            $order = SupplierPurchaseOrder::create([
                // Temporary unique value; replaced with PO-2026-0001 once the id exists.
                'po_number' => 'tmp-'.Str::uuid(),
                'supplier_id' => $data['supplier_id'],
                'po_date' => $data['po_date'],
                'expected_delivery' => $data['expected_delivery'] ?? null,
                'currency' => $data['currency'] ?? 'AED',
                'notes' => $data['notes'] ?? null,
                'status' => 'draft',
                'total_amount' => 0,
                ...$this->charges($data),
            ]);

            $order->update([
                'po_number' => 'PO-'.$order->created_at->year.'-'.str_pad((string) $order->id, 4, '0', STR_PAD_LEFT),
            ]);

            $this->writeItems($order, $data['items']);

            return $order;
        });

        return response()->json($order->load(['supplier', 'items']), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $order = SupplierPurchaseOrder::findOrFail($id);

        if ($order->status !== 'draft') {
            return response()->json(['message' => 'Only draft purchase orders can be edited.'], 409);
        }

        $data = $this->validated($request);

        DB::transaction(function () use ($order, $data): void {
            $order->update([
                'supplier_id' => $data['supplier_id'],
                'po_date' => $data['po_date'],
                'expected_delivery' => $data['expected_delivery'] ?? null,
                'currency' => $data['currency'] ?? 'AED',
                'notes' => $data['notes'] ?? null,
                ...$this->charges($data),
            ]);

            $order->items()->delete();
            $this->writeItems($order, $data['items']);
        });

        return response()->json($order->fresh(['supplier', 'items']));
    }

    public function destroy(int $id): JsonResponse
    {
        $order = SupplierPurchaseOrder::findOrFail($id);

        if ($order->status !== 'draft') {
            return response()->json(['message' => 'Only draft purchase orders can be deleted.'], 409);
        }

        $order->delete();

        return response()->json(['deleted' => (int) $id]);
    }

    /**
     * Receive the order: move every line into stock, log the movement, then
     * record the non-inventory charges (freight, customs, extras) as one expense.
     * The goods value is deliberately NOT an expense — it reaches profit as COGS
     * when the units sell.
     */
    public function receive(int $id): JsonResponse
    {
        $order = DB::transaction(function () use ($id): SupplierPurchaseOrder {
            $order = SupplierPurchaseOrder::with(['items', 'supplier'])
                ->whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless(in_array($order->status, self::OPEN_STATUSES, true), 409, 'This purchase order was already received or cancelled.');

            foreach ($order->items as $item) {
                $product = Product::whereKey($item->product_id)->lockForUpdate()->first();

                if ($product === null) {
                    continue; // Product was deleted after the order was raised.
                }

                $before = (int) $product->stock_quantity;
                $after = $before + (int) $item->quantity;

                $product->update([
                    'stock_quantity' => $after,
                    'stock_status' => Product::stockStatusFor($after, (int) $product->low_stock_threshold),
                    // Keep inventory value aligned with the latest received cost.
                    'cost_price' => (float) $item->unit_cost,
                ]);

                InventoryTransaction::create([
                    'product_id' => $product->id,
                    'quantity_before' => $before,
                    'quantity_after' => $after,
                    'type' => 'restock',
                    'reason' => 'PO '.$order->po_number.' received',
                ]);
            }

            $charges = round((float) $order->shipping_cost + (float) $order->customs_cost + (float) $order->additional_charges, 2);

            if ($charges > 0) {
                Expense::updateOrCreate(
                    ['purchase_order_id' => $order->id],
                    [
                        'name' => 'PO '.$order->po_number.' charges',
                        'category' => 'Logistics',
                        'date' => now()->toDateString(),
                        'amount' => $charges,
                        'currency' => $order->currency ?: 'AED',
                        'supplier_payee' => $order->supplier?->trading_name ?: $order->supplier?->legal_name,
                        'vat_status' => 'not_applicable',
                        'notes' => 'Shipping, customs and additional charges from purchase order receipt.',
                    ],
                );
            }

            $order->update(['status' => 'received']);

            return $order;
        });

        return response()->json([
            'message' => 'Purchase order received and stock updated.',
            'purchase_order' => $order->fresh(['supplier', 'items', 'expense']),
        ]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'po_date' => ['required', 'date'],
            'expected_delivery' => ['nullable', 'date', 'after_or_equal:po_date'],
            'currency' => ['nullable', 'string', 'size:3'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'shipping_cost' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'customs_cost' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'additional_charges' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'items.*.tax' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
        ]);
    }

    /** @param array<string, mixed> $data */
    private function charges(array $data): array
    {
        return [
            'shipping_cost' => (float) ($data['shipping_cost'] ?? 0),
            'customs_cost' => (float) ($data['customs_cost'] ?? 0),
            'additional_charges' => (float) ($data['additional_charges'] ?? 0),
        ];
    }

    /**
     * Totals are always computed here, never taken from the client.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function writeItems(SupplierPurchaseOrder $order, array $items): void
    {
        $total = (float) $order->shipping_cost + (float) $order->customs_cost + (float) $order->additional_charges;
        $names = Product::whereIn('id', array_column($items, 'product_id'))->pluck('name', 'id');

        foreach ($items as $item) {
            $lineTotal = round(
                max(0, (int) $item['quantity'] * (float) $item['unit_cost'] - (float) ($item['discount'] ?? 0)) + (float) ($item['tax'] ?? 0),
                2
            );

            $order->items()->create([
                'product_id' => $item['product_id'],
                'product_name_snapshot' => (string) ($names[$item['product_id']] ?? 'Product'),
                'quantity' => (int) $item['quantity'],
                'unit_cost' => (float) $item['unit_cost'],
                'discount' => (float) ($item['discount'] ?? 0),
                'tax' => (float) ($item['tax'] ?? 0),
                'line_total' => $lineTotal,
            ]);

            $total += $lineTotal;
        }

        $order->update(['total_amount' => round($total, 2)]);
    }
}
