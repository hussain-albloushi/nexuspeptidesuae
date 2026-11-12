<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate(['days' => ['nullable', 'integer', 'in:7,30,90']]);
        $days = (int) ($validated['days'] ?? 30);
        $end = now();
        $start = $end->copy()->startOfDay()->subDays($days - 1);
        $previousStart = $start->copy()->subDays($days);
        $orders = Order::where('created_at', '>=', $start)->where('created_at', '<=', $end);
        $previousOrders = Order::where('created_at', '>=', $previousStart)->where('created_at', '<', $start);
        $current = $this->orderTotals(clone $orders);
        $previous = $this->orderTotals($previousOrders);
        $customers = User::where('role', 'customer');
        $newCustomers = (clone $customers)->whereBetween('created_at', [$start, $end])->count();
        $previousCustomers = (clone $customers)->where('created_at', '>=', $previousStart)->where('created_at', '<', $start)->count();
        $validOrders = (clone $orders)->where('status', '!=', 'cancelled');
        $stock = Product::where('is_active', true);

        $daily = (clone $validOrders)->selectRaw('DATE(created_at) AS date, SUM(total_amount) AS booked')
            ->selectRaw("SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END) AS paid")
            ->groupByRaw('DATE(created_at)')->get()->keyBy('date');
        $trend = [];
        for ($date = $start->copy(); $date->toDateString() <= $end->toDateString(); $date->addDay()) {
            $row = $daily->get($date->toDateString());
            $trend[] = [
                'date' => $date->toDateString(),
                'booked' => round((float) ($row?->booked ?? 0), 2),
                'paid' => round((float) ($row?->paid ?? 0), 2),
            ];
        }

        return response()->json([
            'generated_at' => $end->toIso8601String(),
            'period' => ['days' => $days, 'start' => $start->toDateString(), 'end' => $end->toDateString(), 'timezone' => config('app.timezone')],
            'metrics' => [
                'order_value' => $this->metric($current['booked'], $previous['booked']),
                'paid_sales' => $this->metric($current['paid'], $previous['paid']),
                'orders' => $this->metric($current['orders'], $previous['orders']),
                'customers' => $this->metric($newCustomers, $previousCustomers),
            ],
            'attention' => $this->attention(),
            'trend' => $trend,
            'order_statuses' => (clone $orders)->select('status')->selectRaw('COUNT(*) AS count')->groupBy('status')->get()
                ->map(fn (Order $order): array => ['status' => $order->status, 'count' => (int) $order->getAttribute('count')]),
            'recent_orders' => Order::with('user:id,name')->latest()->orderByDesc('id')->limit(8)
                ->get(['id', 'user_id', 'order_number', 'status', 'payment_status', 'total_amount', 'created_at']),
            'stock_alerts' => (clone $stock)->where(function (Builder $query): void {
                $query->where('stock_quantity', '<=', 0)->orWhereColumn('stock_quantity', '<=', 'low_stock_threshold');
            })->orderBy('stock_quantity')->orderBy('name')->limit(6)
                ->get(['id', 'name', 'sku', 'stock_quantity', 'low_stock_threshold']),
            'top_products' => OrderItem::whereIn('order_id', (clone $validOrders)->select('id'))
                ->select('product_id')->selectRaw('MAX(name) AS name, SUM(quantity) AS units, SUM(total_price) AS sales')
                ->groupBy('product_id')->orderByDesc('units')->orderBy('product_id')->limit(5)->get()
                ->map(fn (OrderItem $item): array => [
                    'product_id' => $item->product_id, 'name' => $item->name,
                    'units' => (int) $item->getAttribute('units'), 'sales' => round((float) $item->getAttribute('sales'), 2),
                ]),
            'recent_customers' => (clone $customers)->latest()->orderByDesc('id')->limit(5)->get(['id', 'name', 'email', 'created_at']),
        ])->header('Cache-Control', 'no-store');
    }

    public function notifications(): JsonResponse
    {
        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'attention' => $this->attention(),
        ])->header('Cache-Control', 'no-store');
    }

    /**
     * @return array{to_process: int, awaiting_payment: int, failed_payments: int, low_stock: int, out_of_stock: int}
     */
    private function attention(): array
    {
        $stock = Product::where('is_active', true);
        $unpaidOrders = Order::where('status', '!=', 'cancelled');

        return [
            'to_process' => Order::whereIn('status', ['pending', 'processing'])->count(),
            'awaiting_payment' => (clone $unpaidOrders)->whereIn('payment_status', ['pending', 'unpaid', 'partially_paid'])->count(),
            'failed_payments' => (clone $unpaidOrders)->where('payment_status', 'failed')->count(),
            'low_stock' => (clone $stock)->where('stock_quantity', '>', 0)->whereColumn('stock_quantity', '<=', 'low_stock_threshold')->count(),
            'out_of_stock' => (clone $stock)->where('stock_quantity', '<=', 0)->count(),
        ];
    }

    private function orderTotals(Builder $query): array
    {
        $totals = $query->selectRaw('COUNT(*) AS orders_count')
            ->selectRaw("COALESCE(SUM(CASE WHEN status != 'cancelled' THEN total_amount ELSE 0 END), 0) AS booked")
            ->selectRaw("COALESCE(SUM(CASE WHEN status != 'cancelled' AND payment_status = 'paid' THEN total_amount ELSE 0 END), 0) AS paid")
            ->first();

        return ['orders' => (int) $totals->orders_count, 'booked' => (float) $totals->booked, 'paid' => (float) $totals->paid];
    }

    private function metric(float $value, float $previous): array
    {
        return [
            'value' => round($value, 2),
            'previous' => round($previous, 2),
            'change_percent' => $previous > 0 ? round(($value - $previous) / $previous * 100, 1) : null,
        ];
    }
}
