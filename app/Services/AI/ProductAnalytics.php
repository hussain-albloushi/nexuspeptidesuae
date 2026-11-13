<?php

namespace App\Services\AI;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Product intelligence metrics and classification. Baseline is the NexusPeptides
 * catalog: products are classified by commercial performance across the active range.
 */
class ProductAnalytics
{
    public static function byRange(DateRange $range): array
    {
        $rows = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->whereNot('orders.status', 'cancelled')
            ->whereBetween('orders.created_at', [$range->start, $range->end])
            ->groupBy('order_items.product_id')
            ->selectRaw(
                'order_items.product_id,
                 MAX(order_items.name) AS name,
                 SUM(order_items.quantity) AS units,
                 SUM(order_items.total_price) AS revenue,
                 SUM(order_items.quantity * COALESCE(products.cost_price, 0)) AS cogs_total,
                 COUNT(DISTINCT order_items.order_id) AS order_count,
                 SUM(order_items.total_price / NULLIF(orders.subtotal, 0) * COALESCE(orders.discount_amount, 0)) AS discount_total,
                 SUM(CASE WHEN orders.discount_amount > 0 THEN 1 ELSE 0 END) AS discounted_order_lines'
            )
            ->get();

        $itemsKeyed = $rows->keyBy('product_id');

        $products = Product::with('category')->get([
            'id', 'name', 'sku', 'category_id', 'price', 'cost_price', 'stock_quantity', 'low_stock_threshold', 'is_active',
        ]);

        $report = [];
        foreach ($products as $p) {
            $item = $itemsKeyed->get($p->id);
            $units = (int) ($item->units ?? 0);
            $revenue = round((float) ($item->revenue ?? 0), 2);
            $cogs = round((float) ($item->cogs_total ?? 0), 2);
            $orders = (int) ($item->order_count ?? 0);
            $discount = round((float) ($item->discount_total ?? 0), 2);
            $grossProfit = round($revenue - $cogs, 2);
            $margin = $revenue > 0 ? round((($revenue - $cogs) / $revenue) * 100, 2) : null;

            $report[] = [
                'id' => (int) $p->id,
                'name' => (string) $p->name,
                'sku' => (string) ($p->sku ?? ''),
                'category' => (string) ($p->category?->name ?? '—'),
                'price' => round((float) ($p->price ?? 0), 2),
                'cost_price' => round((float) ($p->cost_price ?? 0), 2),
                'units_sold' => $units,
                'revenue' => $revenue,
                'gross_profit' => $grossProfit,
                'gross_profit_margin' => $margin,
                'stock_quantity' => (int) ($p->stock_quantity ?? 0),
                'low_stock_threshold' => (int) ($p->low_stock_threshold ?? 5),
                'is_active' => (bool) ($p->is_active ?? true),
                'order_count' => $orders,
                'discount_total' => $discount,
            ];
        }

        return $report;
    }

    /** Classification against configurable thresholds. Returns product ids keyed by class. */
    public static function classify(array $products, array $thresholds): array
    {
        $active = array_values(array_filter($products, fn ($p) => $p['is_active'] ?? true));
        $units = array_map(fn ($p) => (int) $p['units_sold'], $active);
        $revenues = array_map(fn ($p) => (float) $p['revenue'], $active);

        sort($units);
        sort($revenues);

        $unitsTopQ = self::percentile($units, 0.75);
        $revenueMedian = self::percentile($revenues, 0.5);

        $marginThreshold = (float) ($thresholds['ai_profit_margin_warning_threshold'] ?? 15);
        $minSales = (int) ($thresholds['ai_min_product_sales_threshold'] ?? 5);
        $lowStock = (int) ($thresholds['ai_low_stock_threshold'] ?? 10);

        $classified = [
            'best_sellers' => [],
            'high_profit' => [],
            'low_profit' => [],
            'slow_moving' => [],
            'declining' => [],
            'high_revenue_low_profit' => [],
            'high_potential' => [],
            'no_sales' => [],
            'high_discounts' => [],
            'restock_needed' => [],
        ];

        foreach ($active as $p) {
            $id = (int) $p['id'];
            $units = (int) $p['units_sold'];
            $revenue = (float) $p['revenue'];
            $margin = $p['gross_profit_margin'];
            $stock = (int) $p['stock_quantity'];
            $discount = (float) ($p['discount_total'] ?? 0);

            if ($units === 0) {
                $classified['no_sales'][] = $id;
            }

            if ($units > 0) {
                if ($unitsTopQ !== null && $units >= $unitsTopQ) {
                    $classified['best_sellers'][] = $id;
                }
                if ($margin !== null) {
                    if ($margin >= $marginThreshold) {
                        $classified['high_profit'][] = $id;
                    } else {
                        $classified['low_profit'][] = $id;
                    }

                    if ($units >= max($minSales, 1) && $margin >= $marginThreshold && $units <= 10) {
                        // Good margin but low unit velocity — a potential win if promoted.
                        $classified['high_potential'][] = $id;
                    }

                    if ($revenueMedian !== null && $revenue >= $revenueMedian && $margin < $marginThreshold) {
                        // Selling well but margin below the warning threshold.
                        $classified['high_revenue_low_profit'][] = $id;
                    }
                }

                if ($units > 0 && $units <= max($minSales, 1)) {
                    $classified['slow_moving'][] = $id;
                }
            }

            if ($stock >= 0 && $stock <= $lowStock) {
                $classified['restock_needed'][] = $id;
            }

            if ($discount > 0 && ($revenue + $discount) > 0) {
                $discountRatio = $discount / ($revenue + $discount + 0.0001);
                if ($discountRatio >= 0.1) {
                    $classified['high_discounts'][] = $id;
                }
            }
        }

        // Declining: products with negative momentum vs. prior period are computed by TrendDetection;
        // as a lightweight baseline we flag the bottom-decile of revenue-generating velocity.
        $velocities = [];
        foreach ($active as $p) {
            $stock = (int) $p['stock_quantity'];
            $units = (int) $p['units_sold'];
            if ($stock > 0 && $units > 0) {
                $velocities[$p['id']] = $units / $stock;
            }
        }
        asort($velocities);
        $declineCount = (int) ceil(count($velocities) * 0.1);
        foreach (array_keys($velocities) as $id) {
            if ($declineCount <= 0) {
                break;
            }
            $classified['declining'][] = (int) $id;
            $declineCount--;
        }

        return $classified;
    }

    private static function percentile(array $sorted, float $p): ?float
    {
        $n = count($sorted);
        if ($n === 0) {
            return null;
        }

        $idx = $p * ($n - 1);
        $lower = (int) floor($idx);
        $upper = (int) ceil($idx);

        if ($lower === $upper) {
            return (float) $sorted[$lower];
        }

        $w = $idx - $lower;

        return (float) $sorted[$lower] + ($sorted[$upper] - $sorted[$lower]) * $w;
    }
}
