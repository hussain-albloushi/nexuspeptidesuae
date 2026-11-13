<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Setting;
use App\Services\AI\AiPeriod;
use App\Services\AI\AiSummary;
use App\Services\AI\DateRange;
use App\Services\AI\InventoryAnalytics;
use App\Services\AI\ProfitAnalytics;
use App\Services\AI\SalesAnalytics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiOverviewController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $range = AiPeriod::fromRequest($request);
        $previous = $range->previous();

        $thresholds = $this->thresholds();

        // ---- Current period ----
        $unitsSold = SalesAnalytics::unitsSold($range);
        $ordersCount = SalesAnalytics::ordersCount($range);
        $aov = SalesAnalytics::averageOrderValue($range);
        $revenue = SalesAnalytics::revenue($range);
        $discounts = SalesAnalytics::discounts($range);
        $refunds = SalesAnalytics::refunds($range);
        $pnl = ProfitAnalytics::pAndL($range);
        $inventoryValue = InventoryAnalytics::valuation();
        $activeProducts = Product::where('is_active', true)->count();
        $lowStock = collect(InventoryAnalytics::alerts($range, $thresholds))->where('state', 'low_stock')->count();
        $outOfStock = collect(InventoryAnalytics::alerts($range, $thresholds))->where('state', 'out_of_stock')->count();

        // ---- Previous period (for deltas) ----
        $prevRevenue = SalesAnalytics::revenue($previous);
        $prevOrders = SalesAnalytics::ordersCount($previous);
        $prevUnits = SalesAnalytics::unitsSold($previous);
        $prevAov = SalesAnalytics::averageOrderValue($previous);
        $prevDiscounts = SalesAnalytics::discounts($previous);
        $prevRefunds = SalesAnalytics::refunds($previous);
        $prevPnl = ProfitAnalytics::pAndL($previous);

        $kpis = [
            $this->kpi('Revenue', $revenue, $prevRevenue, 'AED', 'Total revenue booked from non-cancelled orders (subtotal + shipping + COD fees).'),
            $this->kpi('Orders', $ordersCount, $prevOrders, '', 'Number of orders placed in the period.', 0),
            $this->kpi('Units Sold', $unitsSold, $prevUnits, '', 'Total units across order items in the period.', 0),
            $this->kpi('Avg Order Value', $aov, $prevAov, 'AED', 'Average order value across non-cancelled orders.'),
            $this->kpi('Gross Profit', $pnl['gross_profit'], $prevPnl['gross_profit'], 'AED', 'Net sales (ex VAT, after discounts) minus COGS.'),
            $this->kpi('Gross Margin', $pnl['gross_profit_margin'] ?? 0, $prevPnl['gross_profit_margin'] ?? 0, '%', 'Gross profit as a percentage of net sales (ex VAT).', 2),
            $this->kpi('Discounts', $discounts, $prevDiscounts, 'AED', 'Total discounts granted across non-cancelled orders.'),
            $this->kpi('Refunds', $refunds, $prevRefunds, 'AED', 'Refunded amounts (manual refund history + approved return requests).'),
            $this->kpi('Inventory Value', $inventoryValue, null, 'AED', 'Current stock valued at product cost price. Point in time, not period-bound.'),
            $this->kpi('Active Products', $activeProducts, null, '', 'Products currently marked active in the catalog.', 0),
            $this->kpi('Low Stock', $lowStock, null, '', 'Products at or below their low-stock threshold (active only).', 0),
            $this->kpi('Out of Stock', $outOfStock, null, '', 'Active products with zero or negative stock.', 0),
        ];

        // ---- Charts ----
        $granularity = $this->granularity($range);
        $overTime = SalesAnalytics::overTime($range, $granularity);
        $prevOverTime = SalesAnalytics::overTime($previous, $granularity);
        $byCategory = SalesAnalytics::byCategory($range);
        $byProduct = collect(SalesAnalytics::byProduct($range))->take(8)->values()->all();

        $todayLabels = array_column($overTime, 'period');
        $comparison = [];
        $max = min(count($overTime), count($prevOverTime));
        for ($i = 0; $i < $max; $i++) {
            $comparison[] = [
                'period' => $overTime[$i]['period'],
                'current' => $overTime[$i]['revenue'],
                'previous' => $prevOverTime[$i]['revenue'],
            ];
        }

        $charts = [
            'revenue_over_time' => ['labels' => $todayLabels, 'series' => array_column($overTime, 'revenue')],
            'orders_over_time' => ['labels' => $todayLabels, 'series' => array_column($overTime, 'orders')],
            'product_sales' => ['labels' => array_column($byProduct, 'product'), 'series' => array_column($byProduct, 'revenue')],
            'category_performance' => ['labels' => array_column($byCategory, 'category'), 'series' => array_column($byCategory, 'revenue')],
            'period_comparison' => $comparison,
        ];

        // Profit over time: derive from daily orders and product margins is approximate;
        // use cumulative margin * revenue curve for a truthful view of the period.
        $profitOverTime = array_map(function ($row) use ($pnl, $revenue) {
            $share = $revenue > 0 ? $row['revenue'] / $revenue : 0;

            return round($pnl['gross_profit'] * $share, 2);
        }, $overTime);

        $charts['profit_over_time'] = ['labels' => $todayLabels, 'series' => $profitOverTime];

        // ---- AI summary ----
        $analytics = [
            'revenue' => $this->compareArray($revenue, $prevRevenue),
            'orders' => $this->compareArray($ordersCount, $prevOrders),
            'units_sold' => $this->compareArray($unitsSold, $prevUnits),
            'average_order_value' => $this->compareArray($aov, $prevAov),
            'gross_profit' => $this->compareArray($pnl['gross_profit'], $prevPnl['gross_profit'] ?? null),
            'gross_margin_percent' => $pnl['gross_profit_margin'],
            'discounts' => $discounts,
            'refunds' => $refunds,
            'low_stock' => $lowStock,
            'out_of_stock' => $outOfStock,
            'top_categories' => array_slice($byCategory, 0, 3),
        ];

        $summary = AiSummary::summarize($analytics, 'Summarize business performance this period vs the prior one. Note the single most important action item.');

        return response()->json([
            'period' => AiPeriod::toResponse($range),
            'comparison_period' => AiPeriod::toResponse($previous),
            'kpis' => $kpis,
            'charts' => $charts,
            'summary' => $summary['text'],
            'summary_source' => $summary['source'],
            'analysis_generated_at' => $summary['generated_at'],
        ]);
    }

    /** @return array<string, mixed> */
    private function kpi(string $label, mixed $value, mixed $previous, string $unit, string $explanation, int $decimals = 2): array
    {
        $isPercent = $unit === '%';
        $delta = null;
        $deltaPercent = null;
        $trend = 'neutral';

        if ($previous !== null) {
            $prev = (float) $previous;
            $delta = round((float) $value - $prev, $decimals + 2);
            if (abs($prev) > 0.000001) {
                $deltaPercent = round((((float) $value - $prev) / abs($prev)) * 100, 2);
            }
            $trend = $delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'flat');
        }

        return [
            'label' => $label,
            'value' => is_numeric($value) ? round((float) $value, $decimals) : $value,
            'unit' => $unit,
            'previous' => $previous !== null ? (is_numeric($previous) ? round((float) $previous, $decimals) : $previous) : null,
            'delta' => $delta,
            'delta_percent' => $deltaPercent,
            'trend' => $trend,
            'explanation' => $explanation,
        ];
    }

    private function compareArray(float|int $current, float|int|null $previous): array
    {
        $comparison = SalesAnalytics::comparison((float) $current, $previous === null ? null : (float) $previous);

        return [
            'value' => $comparison['value'],
            'previous' => $comparison['previous'],
            'delta' => $comparison['delta'],
            'delta_percent' => $comparison['delta_percent'],
        ];
    }

    private function granularity(DateRange $range): string
    {
        $days = $range->start->diffInDays($range->end) + 1;
        if ($days <= 31) {
            return 'daily';
        }
        if ($days <= 120) {
            return 'weekly';
        }

        return 'monthly';
    }

    /** @return array<string, float|int|string> */
    private function thresholds(): array
    {
        $get = fn (string $key, $default) => Setting::where('key', $key)->value('value') ?? $default;

        return [
            'ai_profit_margin_warning_threshold' => (float) $get('ai_profit_margin_warning_threshold', 15),
            'ai_min_product_sales_threshold' => (int) $get('ai_min_product_sales_threshold', 5),
            'ai_low_stock_threshold' => (int) $get('ai_low_stock_threshold', 10),
            'ai_sales_decline_warning_threshold' => (float) $get('ai_sales_decline_warning_threshold', -20),
        ];
    }
}
