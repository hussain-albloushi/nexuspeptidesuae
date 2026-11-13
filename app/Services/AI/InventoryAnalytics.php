<?php

namespace App\Services\AI;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Inventory intelligence metrics computed from the existing stock tracking
 * and inventory transactions tables.
 */
class InventoryAnalytics
{
    /** @return array<int, array<string, mixed>> products needing attention */
    public static function alerts(DateRange $range, array $thresholds = []): array
    {
        $lowStock = (int) ($thresholds['ai_low_stock_threshold'] ?? 10);
        $active = Product::where('is_active', true)->get(['id', 'name', 'sku', 'stock_quantity', 'low_stock_threshold', 'cost_price']);

        $alerts = [];
        foreach ($active as $p) {
            $stock = (int) ($p->stock_quantity ?? 0);
            $skuThreshold = (int) ($p->low_stock_threshold ?? $lowStock);

            if ($stock <= 0) {
                $alerts[] = self::row($p, 'out_of_stock', 0);
            } elseif ($stock <= $skuThreshold) {
                $alerts[] = self::row($p, 'low_stock', $stock);
            }
        }

        return $alerts;
    }

    /** Recommended quantities to reorder, with transparent reasoning. */
    public static function recommendedReorders(DateRange $range, array $thresholds = []): array
    {
        $products = Product::where('is_active', true)
            ->where('stock_quantity', '<=', 0)
            ->get(['id', 'name', 'sku', 'stock_quantity', 'cost_price']);

        return $products->map(fn ($p) => [
            'product_id' => $p->id,
            'name' => $p->name,
            'sku' => $p->sku ?? '',
            'suggested_quantity' => max(0, (int) (($p->stock_quantity ?? 0) * -1) + 10),
            'reason' => 'Product is out of stock. Reorder to restore availability plus a 10-unit buffer.',
            'stock_quantity' => (int) ($p->stock_quantity ?? 0),
        ])->values()->all();
    }

    /** Stock valuation based on purchase cost where it exists. */
    public static function valuation(): float
    {
        return (float) Product::whereNotNull('cost_price')
            ->whereNotNull('stock_quantity')
            ->where('stock_quantity', '>', 0)
            ->sum(DB::raw('stock_quantity * COALESCE(cost_price, 0)'));
    }

    private static function row(Product $p, string $state, int $stock): array
    {
        return [
            'product_id' => $p->id,
            'name' => $p->name,
            'sku' => $p->sku ?? '',
            'stock_quantity' => $stock,
            'state' => $state,
        ];
    }
}
