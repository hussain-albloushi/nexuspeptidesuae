<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\AI\AiPeriod;
use App\Services\AI\AiSummary;
use App\Services\AI\CustomerAnalytics;
use App\Services\AI\DateRange;
use App\Services\AI\SalesAnalytics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BusinessAnalyticsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $range = AiPeriod::fromRequest($request);
        $previous = $range->previous();

        $customer = CustomerAnalytics::insights($range);
        $previousCustomer = CustomerAnalytics::insights($previous);

        $ordersByStatus = Order::whereBetween('created_at', [$range->start, $range->end])
            ->selectRaw('status, COUNT(*) AS count')
            ->groupBy('status')->pluck('count', 'status')->all();

        $paymentMethods = Order::whereNot('status', 'cancelled')
            ->whereBetween('created_at', [$range->start, $range->end])
            ->selectRaw("COALESCE(payment_method, 'unknown') AS method, COUNT(*) AS count, COALESCE(SUM(total_amount), 0) AS revenue")
            ->groupBy('method')->orderByDesc('revenue')->get()
            ->map(fn ($r) => ['method' => (string) $r->method, 'orders' => (int) $r->count, 'revenue' => round((float) $r->revenue, 2)])
            ->all();

        $discountUsage = Order::whereNot('status', 'cancelled')
            ->whereBetween('created_at', [$range->start, $range->end])
            ->selectRaw('SUM(discount_amount) AS discounts, COUNT(*) AS orders, SUM(CASE WHEN discount_amount > 0 THEN 1 ELSE 0 END) AS discounted_orders')
            ->first();

        $byCategory = SalesAnalytics::byCategory($range);
        $topProducts = collect(SalesAnalytics::byProduct($range))->take(10)->values()->all();
        $topCustomers = array_slice($customer['top_customers'], 0, 10);

        // Geographic distribution — best effort from users' saved address / order shipping address.
        $geo = $this->geographicDistribution($range);

        $daysInRange = max(1, $range->start->diffInDays($range->end) + 1);

        $analytics = [
            'revenue' => $this->compareArray(SalesAnalytics::revenue($range), SalesAnalytics::revenue($previous)),
            'orders' => $this->compareArray(SalesAnalytics::ordersCount($range), SalesAnalytics::ordersCount($previous)),
            'aov' => $this->compareArray(SalesAnalytics::averageOrderValue($range), SalesAnalytics::averageOrderValue($previous)),
            'active_customers' => $this->compareArray($customer['active_customers'], $previousCustomer['active_customers']),
            'repeat_rate' => $customer['repeat_rate'],
            'top_categories' => array_slice($byCategory, 0, 3),
        ];
        $summary = AiSummary::summarize($analytics, 'Summarise customer behaviour and purchasing patterns. Highlight AOV, repeat purchase rate and one concrete growth action.');

        return response()->json([
            'period' => AiPeriod::toResponse($range),
            'comparison_period' => AiPeriod::toResponse($previous),
            'customers' => array_merge($customer, [
                'repeat_rate_previous' => $previousCustomer['repeat_rate'],
                'new_customers_previous' => $previousCustomer['new_customers'],
            ]),
            'orders_by_status' => $ordersByStatus,
            'payment_methods' => $paymentMethods,
            'discounts' => [
                'total' => round((float) ($discountUsage->discounts ?? 0), 2),
                'discounted_orders' => (int) ($discountUsage->discounted_orders ?? 0),
                'orders' => (int) ($discountUsage->orders ?? 0),
            ],
            'top_categories' => $byCategory,
            'top_products' => $topProducts,
            'top_customers' => $topCustomers,
            'geographic' => $geo,
            'revenue_per_active_customer' => $customer['revenue_per_active_customer'],
            'days_in_range' => $daysInRange,
            'repeat_rate' => $customer['repeat_rate'],
            'average_order_value' => $customer['average_order_value'],
            'summary' => $summary['text'],
            'summary_source' => $summary['source'],
            'analysis_generated_at' => $summary['generated_at'],
        ]);
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

    /** Coarse geographic grouping from order shipping addresses. */
    private function geographicDistribution(DateRange $range): array
    {
        try {
            $rows = DB::table('orders')
                ->whereNot('status', 'cancelled')
                ->whereBetween('created_at', [$range->start, $range->end])
                ->whereNotNull('shipping_address')
                ->limit(2000)
                ->pluck('shipping_address');

            $counts = [];
            foreach ($rows as $address) {
                $text = (string) $address;
                // Saved addresses may be JSON ({"city": "Dubai", ...}) or free text ending in "..., City".
                $city = null;
                $decoded = json_decode($text, true);
                if (is_array($decoded)) {
                    $city = $decoded['city'] ?? $decoded['region'] ?? $decoded['state'] ?? null;
                } else {
                    $parts = array_map('trim', explode(',', $text));
                    $city = $parts[array_key_last($parts)] ?? null;
                }

                $city = trim((string) $city, " \\\"'");
                if ($city === '' || strlen($city) > 60) {
                    continue;
                }
                $counts[$city] = ($counts[$city] ?? 0) + 1;
            }

            arsort($counts);

            return collect($counts)->take(10)->map(fn ($count, $city) => ['location' => (string) $city, 'orders' => (int) $count])->values()->all();
        } catch (\Throwable) {
            return [];
        }
    }
}
