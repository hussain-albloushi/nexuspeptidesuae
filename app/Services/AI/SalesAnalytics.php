<?php

namespace App\Services\AI;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\ReturnRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Deterministic sales metrics computed directly from the store's orders,
 * order items, products and users tabellen. No LLM is used for numbers.
 */
class SalesAnalytics
{
    /**
     * Total revenue booked in a range (non-cancelled orders).
     * Gross revenue here is subtotal + shipping + COD fees — the same scope the
     * existing dashboard uses.
     */
    public static function revenue(DateRange $range): float
    {
        return (float) Cache::remember('ai:analytics:sales:revenue:'.$range->start->toDateString().':'.$range->end->toDateString(), 60, function () use ($range) {
            return (float) Order::whereNot('status', 'cancelled')
                ->whereBetween('created_at', [$range->start, $range->end])
                ->sum(DB::raw('COALESCE(subtotal, 0) + COALESCE(shipping_amount, 0) + COALESCE(cod_fee, 0)'));
        });
    }

    /** Revenue excluding shipping & COD fees (i.e. merchandise revenue). */
    public static function merchandiseRevenue(DateRange $range): float
    {
        return (float) Order::whereNot('status', 'cancelled')
            ->whereBetween('created_at', [$range->start, $range->end])
            ->sum('subtotal');
    }

    public static function discounts(DateRange $range): float
    {
        return (float) Order::whereNot('status', 'cancelled')
            ->whereBetween('created_at', [$range->start, $range->end])
            ->sum('discount_amount');
    }

    public static function taxCollected(DateRange $range): float
    {
        return (float) Order::whereNot('status', 'cancelled')
            ->whereBetween('created_at', [$range->start, $range->end])
            ->sum('tax_amount');
    }

    /** Units sold in the range. */
    public static function unitsSold(DateRange $range): int
    {
        return (int) OrderItem::whereIn('order_id',
            Order::whereNot('status', 'cancelled')->whereBetween('created_at', [$range->start, $range->end])->select('id')
        )->sum('quantity');
    }

    /** Order count (including cancelled), plus cancelled count separately. */
    public static function ordersCount(DateRange $range): int
    {
        return (int) Cache::remember('ai:analytics:sales:orders:'.$range->start->toDateString().':'.$range->end->toDateString(), 60, function () use ($range) {
            return (int) Order::whereBetween('created_at', [$range->start, $range->end])->count();
        });
    }

    public static function averageOrderValue(DateRange $range): float
    {
        $row = Order::whereNot('status', 'cancelled')
            ->whereBetween('created_at', [$range->start, $range->end])
            ->selectRaw('COUNT(*) AS orders, COALESCE(SUM(total_amount), 0) AS booked')
            ->first();

        $orders = (int) ($row?->orders ?? 0);

        return $orders > 0 ? round((float) ($row?->booked ?? 0) / $orders, 2) : 0.0;
    }

    /** Refunds in the range: manual refund entries + approved return requests. */
    public static function refunds(DateRange $range): float
    {
        $manualAmount = OrderStatusHistory::where('created_at', '>=', $range->start)
            ->where('created_at', '<=', $range->end)
            ->where('notes', 'like', '%Refund of AED%')
            ->get()
            ->sum(fn ($row) => (float) preg_replace('/.*Refund of AED ([0-9.]+).*/', '$1', (string) $row->notes));

        $returnAmount = ReturnRequest::whereBetween('created_at', [$range->start, $range->end])
            ->where('status', ReturnRequest::STATUS_APPROVED)
            ->sum('refund_amount');

        return round($manualAmount + (float) $returnAmount, 2);
    }

    /**
     * Comparison object for a value, returning the current value, the previous
     * value, the absolute delta and the percent delta.
     */
    public static function comparison(float $current, ?float $previous): array
    {
        $delta = $previous === null ? null : round($current - $previous, 2);
        $percent = ($previous !== null && abs($previous) > 0.000001)
            ? round((($current - $previous) / abs($previous)) * 100, 2)
            : null;

        return ['value' => $current, 'previous' => $previous, 'delta' => $delta, 'delta_percent' => $percent];
    }

    /** Revenue / order / AOV trend lines for charts. */
    public static function overTime(DateRange $range, string $granularity = 'daily'): array
    {
        $orders = Order::whereNot('status', 'cancelled')
            ->whereBetween('created_at', [$range->start, $range->end]);

        $driver = DB::connection()->getDriverName();
        $mysql = $driver === 'mysql';

        switch ($granularity) {
            case 'weekly':
                $period = $mysql ? 'DATE_FORMAT(created_at, "%x-%v")' : "strftime('%Y-%W', created_at)";
                break;
            case 'monthly':
                $period = $mysql ? 'DATE_FORMAT(created_at, "%Y-%m")' : "strftime('%Y-%m', created_at)";
                break;
            case 'quarterly':
                $period = $mysql
                    ? 'CONCAT(YEAR(created_at), "-Q", QUARTER(created_at))'
                    : "strftime('%Y', created_at) || '-Q' || ((CAST(strftime('%m', created_at) AS INTEGER) + 2) / 3)";
                break;
            case 'annual':
                $period = $mysql ? 'YEAR(created_at)' : "strftime('%Y', created_at)";
                break;
            default:
                $period = 'DATE(created_at)';
        }

        $orders = $orders
            ->selectRaw("{$period} AS period, COUNT(*) AS orders, COALESCE(SUM(total_amount), 0) AS revenue")
            ->groupBy('period')
            ->orderBy('period');

        return $orders->get()->map(fn ($row): array => [
            'period' => (string) $row->period,
            'orders' => (int) $row->orders,
            'revenue' => round((float) $row->revenue, 2),
        ])->all();
    }

    /** Category performance in the range. */
    public static function byCategory(DateRange $range): array
    {
        return Cache::remember('ai:analytics:sales:bycategory:'.$range->start->toDateString().':'.$range->end->toDateString(), 60, function () use ($range) {
            return DB::table('order_items')
                ->join('products', 'order_items.product_id', '=', 'products.id')
                ->join('categories', 'products.category_id', '=', 'categories.id')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->whereNot('orders.status', 'cancelled')
                ->whereBetween('orders.created_at', [$range->start, $range->end])
                ->groupBy('categories.name')
                ->selectRaw('categories.name AS category, SUM(order_items.quantity) AS units, SUM(order_items.quantity * order_items.price) AS revenue')
                ->orderByDesc('revenue')
                ->get()
                ->map(fn ($row) => ['category' => (string) $row->category, 'units' => (int) $row->units, 'revenue' => round((float) $row->revenue, 2)])
                ->all();
        });
    }

    /** Products generating revenue in the range, sorted by revenue desc. */
    public static function byProduct(DateRange $range): array
    {
        return DB::table('order_items')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->whereNot('orders.status', 'cancelled')
            ->whereBetween('orders.created_at', [$range->start, $range->end])
            ->groupBy('products.name')
            ->selectRaw('products.name AS product, SUM(order_items.quantity) AS units, SUM(order_items.total_price) AS revenue, SUM(order_items.quantity * COALESCE(products.cost_price, 0)) AS cogs')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn ($row) => [
                'product' => (string) $row->product,
                'units' => (int) $row->units,
                'revenue' => round((float) $row->revenue, 2),
                'gross_profit' => round((float) $row->revenue - (float) $row->cogs, 2),
                'margin_percent' => ((float) $row->revenue > 0 ? round(((float) $row->revenue - (float) $row->cogs) / (float) $row->revenue * 100, 2) : null),
            ])
            ->all();
    }

    /** Customers ranked by revenue in the range. */
    public static function byCustomer(DateRange $range): array
    {
        return DB::table('orders')
            ->join('users', 'orders.user_id', '=', 'users.id')
            ->whereNot('orders.status', 'cancelled')
            ->whereBetween('orders.created_at', [$range->start, $range->end])
            ->groupBy('users.id', 'users.name', 'users.email')
            ->selectRaw('users.name AS customer, users.email AS email, COUNT(*) AS orders, COALESCE(SUM(orders.total_amount), 0) AS revenue')
            ->orderByDesc('revenue')
            ->limit(50)
            ->get()
            ->map(fn ($row) => [
                'customer' => (string) $row->customer,
                'email' => (string) $row->email,
                'orders' => (int) $row->orders,
                'revenue' => round((float) $row->revenue, 2),
            ])
            ->all();
    }
}
