<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\AI\AiPeriod;
use App\Services\AI\DateRange;
use App\Services\AI\MovingAverageForecast;
use App\Services\AI\ProfitAnalytics;
use App\Services\AI\SalesAnalytics;
use App\Services\AI\TrendDetection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesIntelligenceController extends Controller
{
    public function trend(Request $request): JsonResponse
    {
        $range = AiPeriod::fromRequest($request);
        $granularity = (string) $request->input('granularity', 'daily');
        if (! in_array($granularity, ['daily', 'weekly', 'monthly', 'quarterly', 'annual'], true)) {
            $granularity = 'daily';
        }

        $series = SalesAnalytics::overTime($range, $granularity);
        $previous = $range->previous();
        $prevSeries = SalesAnalytics::overTime($previous, $granularity);

        // Trend slope on daily series (always compute daily for slope stability)
        $daily = SalesAnalytics::overTime($range, 'daily');
        $trend = TrendDetection::linearTrendSlope(array_column($daily, 'revenue'));
        $anomalies = TrendDetection::detectAnomalies(array_column($daily, 'revenue'), 7);
        $seasonality = TrendDetection::seasonalPattern($range);

        $revenue = SalesAnalytics::revenue($range);
        $prevRevenue = SalesAnalytics::revenue($previous);
        $deltaPercent = $prevRevenue > 0 ? round((($revenue - $prevRevenue) / $prevRevenue) * 100, 2) : null;

        return response()->json([
            'period' => AiPeriod::toResponse($range),
            'comparison_period' => AiPeriod::toResponse($previous),
            'granularity' => $granularity,
            'series' => $series,
            'previous_series' => $prevSeries,
            'trend' => [
                'slope' => $trend['slope'],
                'r2' => $trend['r2'],
                'direction' => $trend['slope'] > 0.01 ? 'increasing' : ($trend['slope'] < -0.01 ? 'declining' : 'flat'),
                'revenue_delta_percent' => $deltaPercent,
            ],
            'anomalies' => $anomalies['anomalies'],
            'anomalies_note' => $anomalies['note'],
            'seasonality' => $seasonality,
        ]);
    }

    public function breakdown(Request $request): JsonResponse
    {
        $range = AiPeriod::fromRequest($request);

        $byStatus = Order::whereBetween('created_at', [$range->start, $range->end])
            ->selectRaw('status, COUNT(*) AS count')
            ->groupBy('status')->pluck('count', 'status')->all();

        $paymentMethods = Order::whereNot('status', 'cancelled')
            ->whereBetween('created_at', [$range->start, $range->end])
            ->selectRaw("COALESCE(payment_method, 'unknown') AS method, COUNT(*) AS count, COALESCE(SUM(total_amount), 0) AS revenue, COALESCE(SUM(discount_amount), 0) AS discounts")
            ->groupBy('method')->orderByDesc('revenue')->get()
            ->map(fn ($r) => ['method' => (string) $r->method, 'orders' => (int) $r->count, 'revenue' => round((float) $r->revenue, 2), 'discounts' => round((float) $r->discounts, 2)])
            ->all();

        $discountedOrders = Order::whereNot('status', 'cancelled')
            ->whereBetween('created_at', [$range->start, $range->end])
            ->selectRaw('COUNT(*) AS orders, SUM(CASE WHEN discount_amount > 0 THEN 1 ELSE 0 END) AS discounted, SUM(discount_amount) AS discounts')
            ->first();

        $productMargins = collect(ProfitAnalytics::productProfitability($range))->take(10)->values()->all();

        return response()->json([
            'period' => AiPeriod::toResponse($range),
            'by_category' => SalesAnalytics::byCategory($range),
            'by_product' => collect(SalesAnalytics::byProduct($range))->take(15)->values()->all(),
            'by_customer' => collect(SalesAnalytics::byCustomer($range))->take(15)->values()->all(),
            'by_status' => $byStatus,
            'by_payment_method' => $paymentMethods,
            'discount_usage' => [
                'orders' => (int) ($discountedOrders->orders ?? 0),
                'discounted_orders' => (int) ($discountedOrders->discounted ?? 0),
                'discounts' => round((float) ($discountedOrders->discounts ?? 0), 2),
            ],
            'top_profitable_products' => $productMargins,
        ]);
    }

    public function compare(Request $request): JsonResponse
    {
        $data = $request->validate([
            'start_a' => ['required', 'date'],
            'end_a' => ['required', 'date', 'after_or_equal:start_a'],
            'start_b' => ['required', 'date'],
            'end_b' => ['required', 'date', 'after_or_equal:start_b'],
        ]);

        $a = DateRange::from((string) $data['start_a'], (string) $data['end_a']);
        $b = DateRange::from((string) $data['start_b'], (string) $data['end_b']);

        $revA = SalesAnalytics::revenue($a);
        $revB = SalesAnalytics::revenue($b);

        $contributors = TrendDetection::contributors(SalesAnalytics::byProduct($a), SalesAnalytics::byProduct($b));

        return response()->json([
            'period_a' => AiPeriod::toResponse($a),
            'period_b' => AiPeriod::toResponse($b),
            'period_a_summary' => $this->summary($a),
            'period_b_summary' => $this->summary($b),
            'revenue_delta' => round($revA - $revB, 2),
            'revenue_delta_percent' => $revB > 0 ? round((($revA - $revB) / $revB) * 100, 2) : null,
            'orders_delta' => SalesAnalytics::ordersCount($a) - SalesAnalytics::ordersCount($b),
            'units_delta' => SalesAnalytics::unitsSold($a) - SalesAnalytics::unitsSold($b),
            'aov_delta' => round(SalesAnalytics::averageOrderValue($a) - SalesAnalytics::averageOrderValue($b), 2),
            'contributors' => $contributors,
        ]);
    }

    public function forecast(Request $request): JsonResponse
    {
        $range = AiPeriod::fromRequest($request);

        // Use up to 120 days of history ending at range->end for stable forecasting
        $historyEnd = $range->end;
        $historyStart = $historyEnd->copy()->subDays(119);
        $historyRange = DateRange::from($historyStart->toDateString(), $historyEnd->toDateString());

        $daily = SalesAnalytics::overTime($historyRange, 'daily');
        $series = array_map(fn ($r) => ['date' => $r['period'], 'revenue' => (float) $r['revenue']], $daily);

        $forecast = (new MovingAverageForecast)->forecast($series);

        // Product demand outlook: units per product over the history window
        $demand = collect(SalesAnalytics::byProduct($historyRange))
            ->sortByDesc('units')
            ->take(10)
            ->values()
            ->map(fn ($r) => ['product' => $r['product'], 'units_in_history' => $r['units'], 'projected_14d_units' => round($r['units'] / 120 * 14, 1)])
            ->all();

        return response()->json([
            'period' => AiPeriod::toResponse($range),
            'history_days' => count($series),
            'forecast' => $forecast,
            'product_demand' => $demand,
        ]);
    }

    private function summary(DateRange $range): array
    {
        return [
            'revenue' => round(SalesAnalytics::revenue($range), 2),
            'orders' => SalesAnalytics::ordersCount($range),
            'units_sold' => SalesAnalytics::unitsSold($range),
            'average_order_value' => SalesAnalytics::averageOrderValue($range),
            'discounts' => round(SalesAnalytics::discounts($range), 2),
            'refunds' => SalesAnalytics::refunds($range),
        ];
    }
}
