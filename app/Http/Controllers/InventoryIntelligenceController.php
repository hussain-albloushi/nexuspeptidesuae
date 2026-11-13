<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Setting;
use App\Services\AI\AiPeriod;
use App\Services\AI\InventoryAnalytics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryIntelligenceController extends Controller
{
    public function stock(Request $request): JsonResponse
    {
        $range = AiPeriod::fromRequest($request);
        $days = max(1, $range->start->diffInDays($range->end) + 1);

        // Units sold per product across the range for velocity
        $velocity = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->whereNot('orders.status', 'cancelled')
            ->whereBetween('orders.created_at', [$range->start, $range->end])
            ->groupBy('order_items.product_id')
            ->selectRaw('order_items.product_id, SUM(order_items.quantity) AS units')
            ->pluck('units', 'product_id');

        $products = Product::where('is_active', true)->get([
            'id', 'name', 'sku', 'stock_quantity', 'low_stock_threshold', 'cost_price', 'price',
        ]);

        $rows = [];
        foreach ($products as $p) {
            $unitsSold = (int) ($velocity[$p->id] ?? 0);
            $avgDaily = round($unitsSold / $days, 2);
            $stock = (int) ($p->stock_quantity ?? 0);
            $daysRemaining = $avgDaily > 0 ? round($stock / $avgDaily, 1) : null;
            $value = round($stock * (float) ($p->cost_price ?? 0), 2);

            $rows[] = [
                'id' => (int) $p->id,
                'name' => (string) $p->name,
                'sku' => (string) ($p->sku ?? ''),
                'stock_quantity' => $stock,
                'low_stock_threshold' => (int) ($p->low_stock_threshold ?? 5),
                'units_sold_in_period' => $unitsSold,
                'average_daily_sales' => $avgDaily,
                'days_of_inventory' => $daysRemaining,
                'stock_value' => $value,
            ];
        }

        usort($rows, fn ($a, $b) => ($a['days_of_inventory'] ?? PHP_INT_MAX) <=> ($b['days_of_inventory'] ?? PHP_INT_MAX));

        return response()->json([
            'period' => AiPeriod::toResponse($range),
            'products' => $rows,
            'total_stock_value' => round(array_sum(array_column($rows, 'stock_value')), 2),
        ]);
    }

    public function alerts(Request $request): JsonResponse
    {
        $range = AiPeriod::fromRequest($request);
        $thresholds = ['ai_low_stock_threshold' => (int) (Setting::where('key', 'ai_low_stock_threshold')->value('value') ?: 10)];

        $alerts = InventoryAnalytics::alerts($range, $thresholds);
        $reorders = InventoryAnalytics::recommendedReorders($range, $thresholds);

        // Overstock: high stock, very low velocity
        $velocity = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->whereNot('orders.status', 'cancelled')
            ->whereBetween('orders.created_at', [$range->start, $range->end])
            ->groupBy('order_items.product_id')
            ->selectRaw('order_items.product_id, SUM(order_items.quantity) AS units')
            ->pluck('units', 'product_id');

        $overstocked = Product::where('is_active', true)
            ->where('stock_quantity', '>', 20)
            ->get(['id', 'name', 'sku', 'stock_quantity', 'cost_price'])
            ->filter(fn ($p) => (int) ($velocity[$p->id] ?? 0) <= 2)
            ->map(fn ($p) => [
                'product_id' => (int) $p->id,
                'name' => (string) $p->name,
                'sku' => (string) ($p->sku ?? ''),
                'stock_quantity' => (int) $p->stock_quantity,
                'units_in_period' => (int) ($velocity[$p->id] ?? 0),
                'stock_value' => round((int) $p->stock_quantity * (float) ($p->cost_price ?? 0), 2),
            ])
            ->values()
            ->all();

        return response()->json([
            'period' => AiPeriod::toResponse($range),
            'out_of_stock' => array_values(array_filter($alerts, fn ($a) => $a['state'] === 'out_of_stock')),
            'low_stock' => array_values(array_filter($alerts, fn ($a) => $a['state'] === 'low_stock')),
            'overstocked' => $overstocked,
            'recommended_reorders' => $reorders,
        ]);
    }

    public function movement(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $query = DB::table('inventory_transactions')
            ->join('products', 'inventory_transactions.product_id', '=', 'products.id')
            ->select('inventory_transactions.*', 'products.name AS product_name', 'products.sku')
            ->orderByDesc('inventory_transactions.created_at')
            ->limit((int) $request->input('limit', 50));

        if ($request->filled('product_id')) {
            $query->where('inventory_transactions.product_id', (int) $request->input('product_id'));
        }

        $rows = $query->get()->map(fn ($r) => [
            'id' => (int) $r->id,
            'product_id' => (int) $r->product_id,
            'product' => (string) $r->product_name,
            'sku' => (string) ($r->sku ?? ''),
            'type' => (string) $r->type,
            'quantity_before' => (int) $r->quantity_before,
            'quantity_after' => (int) $r->quantity_after,
            'delta' => (int) $r->quantity_after - (int) $r->quantity_before,
            'reason' => $r->reason,
            'created_at' => (string) $r->created_at,
        ])->all();

        return response()->json(['movement' => $rows]);
    }

    public function valuation(Request $request): JsonResponse
    {
        return response()->json([
            'stock_value' => InventoryAnalytics::valuation(),
            'units_on_hand' => (int) Product::whereNotNull('stock_quantity')->sum('stock_quantity'),
            'products_with_cost' => Product::whereNotNull('cost_price')->count(),
            'note' => 'Valuation uses product cost_price only. Products without a cost price are excluded rather than guessed.',
        ]);
    }
}
