<?php

namespace App\Services\AI;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CustomerAnalytics
{
    /** @return array<string, mixed> */
    public static function insights(DateRange $range): array
    {
        $orders = DB::table('orders')
            ->whereNot('status', 'cancelled')
            ->whereBetween('created_at', [$range->start, $range->end])
            ->groupBy('user_id')
            ->selectRaw('user_id, COUNT(*) AS orders, SUM(total_amount) AS revenue')
            ->get();

        $totalCustomers = (int) DB::table('users')->where('role', 'customer')->count();
        $activeCustomers = (int) $orders->count();

        $repeatedCustomers = (int) $orders->where('orders', '>', 1)->count();

        // Revenue per active customer
        $revenuePerActive = $activeCustomers > 0
            ? round((float) $orders->sum('revenue') / $activeCustomers, 2)
            : 0.0;

        // Repeat purchase rate
        $repeatRate = $activeCustomers > 0
            ? round(($repeatedCustomers / $activeCustomers) * 100, 2)
            : null;

        // AOV (including COD fees and extras) for orders in range
        $aov = (float) DB::table('orders')
            ->whereNot('status', 'cancelled')
            ->whereBetween('created_at', [$range->start, $range->end])
            ->avg('total_amount');

        // Top customers by revenue
        $topCustomers = DB::table('orders')
            ->join('users', 'orders.user_id', '=', 'users.id')
            ->whereNot('orders.status', 'cancelled')
            ->whereBetween('orders.created_at', [$range->start, $range->end])
            ->groupBy('users.id', 'users.name', 'users.email')
            ->selectRaw('users.name AS name, users.email AS email, COUNT(*) AS orders, SUM(orders.total_amount) AS revenue')
            ->orderByDesc('revenue')
            ->limit(20)
            ->get()
            ->map(fn ($row) => [
                'name' => (string) $row->name,
                'email' => (string) $row->email,
                'orders' => (int) $row->orders,
                'revenue' => round((float) $row->revenue, 2),
            ])
            ->all();

        // New vs returning customers (based on first-order date within the range)
        $firstOrders = DB::table('orders')
            ->selectRaw('user_id, MIN(created_at) AS first_at')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $newInRange = 0;
        foreach ($orders as $o) {
            $first = $firstOrders->get($o->user_id);
            if ($first && $first->first_at >= $range->start && $first->first_at <= $range->end) {
                $newInRange++;
            }
        }

        $returningInRange = max(0, $activeCustomers - $newInRange);

        return [
            'total_customers' => $totalCustomers,
            'active_customers' => $activeCustomers,
            'new_customers' => $newInRange,
            'returning_customers' => $returningInRange,
            'repeat_rate' => $repeatRate,
            'average_order_value' => round((float) $aov, 2),
            'revenue_per_active_customer' => $revenuePerActive,
            'top_customers' => $topCustomers,
        ];
    }

    /** Repeat rate and basic RFM grouping, fully static approach. */
    public static function rfmSegments(DateRange $range): array
    {
        $rows = DB::table('orders')
            ->join('users', 'orders.user_id', '=', 'users.id')
            ->whereNot('orders.status', 'cancelled')
            ->whereBetween('orders.created_at', [$range->start, $range->end])
            ->groupBy('users.id', 'users.name')
            ->selectRaw('users.id AS user_id, users.name AS name, COUNT(*) AS frequency, SUM(orders.total_amount) AS monetary, MAX(orders.created_at) AS last_at')
            ->get();

        $now = now();

        $customers = $rows->map(fn ($r) => [
            'user_id' => (int) $r->user_id,
            'name' => (string) $r->name,
            'frequency' => (int) $r->frequency,
            'monetary' => round((float) $r->monetary, 2),
            'recency_days' => max(0, (int) $now->diffInDays(Carbon::parse($r->last_at))),
        ])->all();

        // Simple transparent segments
        $champions = array_values(array_filter($customers, fn ($c) => $c['frequency'] >= 3 && $c['monetary'] >= 1000));
        $loyal = array_values(array_filter($customers, fn ($c) => $c['frequency'] >= 2 && $c['monetary'] < 1000));
        $atRisk = array_values(array_filter($customers, fn ($c) => $c['frequency'] >= 2 && $c['recency_days'] > 60));
        $new = array_values(array_filter($customers, fn ($c) => $c['frequency'] === 1 && $c['recency_days'] <= 30));

        return [
            'champions' => $champions,
            'loyal' => $loyal,
            'at_risk' => $atRisk,
            'new' => $new,
        ];
    }
}
