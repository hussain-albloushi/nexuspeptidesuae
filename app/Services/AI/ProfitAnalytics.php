<?php

namespace App\Services\AI;

use App\Models\Expense;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Profitability computed from real records: orders, order items, product cost,
 * VAT from the order, refunds, and recorded expenses.
 */
class ProfitAnalytics
{
    /** @return array<string, mixed> basic profitability tree */
    public static function pAndL(DateRange $range): array
    {
        $orderBase = Order::whereNot('status', 'cancelled')
            ->whereBetween('created_at', [$range->start, $range->end]);

        $grossRevenue = (float) (clone $orderBase)->sum(DB::raw('COALESCE(subtotal,0) + COALESCE(shipping_amount,0) + COALESCE(cod_fee,0)'));
        $discounts = (float) (clone $orderBase)->sum('discount_amount');
        $tax = (float) (clone $orderBase)->sum('tax_amount');
        // VAT is reported informational only (never double-counted in revenue), so net sales follow the existing accounting convention: gross − discounts.
        $netSalesExVat = round($grossRevenue - $discounts, 2);

        $itemsCogs = (float) DB::table('order_items')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->whereNot('orders.status', 'cancelled')
            ->whereBetween('orders.created_at', [$range->start, $range->end])
            ->sum(DB::raw('order_items.quantity * COALESCE(products.cost_price, 0)'));

        $shippingIncome = (float) (clone $orderBase)->sum('shipping_amount');
        $shippingExpenses = (float) Expense::whereBetween('date', [$range->start, $range->end])
            ->where(fn ($q) => $q->where('category', 'like', '%ship%')->orWhere('name', 'like', '%ship%')->orWhere('notes', 'like', '%ship%'))
            ->sum('amount');

        $refunds = SalesAnalytics::refunds($range);

        $recordedExpenses = (float) Expense::whereBetween('date', [$range->start, $range->end])->sum('amount');

        $grossProfit = round($netSalesExVat - $itemsCogs, 2);
        $grossMargin = $netSalesExVat > 0 ? round(($grossProfit / $netSalesExVat) * 100, 2) : null;

        // Net profit is shown only when expense data is complete enough to claim it.
        // Recorded expenses already include any shipping-category expense rows, so shipping
        // expenses are reported informational only and never subtracted twice.
        $expenseDataComplete = Expense::count() > 0;
        $netOperatingProfit = $expenseDataComplete ? round($grossProfit - $recordedExpenses - $refunds, 2) : null;

        return [
            'gross_revenue' => round($grossRevenue, 2),
            'discounts' => round($discounts, 2),
            'net_sales_ex_vat' => $netSalesExVat,
            'tax_collected' => round($tax, 2),
            'cost_of_goods_sold' => round($itemsCogs, 2),
            'gross_profit' => $grossProfit,
            'gross_profit_margin' => $grossMargin,
            'shipping_income' => round($shippingIncome, 2),
            'shipping_expenses' => round($shippingExpenses, 2),
            'payment_gateway_fees' => null,
            'refunds' => $refunds,
            'recorded_expenses' => round($recordedExpenses, 2),
            'net_operating_profit' => $netOperatingProfit,
            'expense_data_complete' => (bool) $expenseDataComplete,
            'note' => $expenseDataComplete ? null : 'Net profit is not shown because no expenses are recorded; gross profit (after COGS only) is the defensible figure.',
        ];
    }

    /** Product-level gross profit breakdown. */
    public static function productProfitability(DateRange $range): array
    {
        $rows = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->whereNot('orders.status', 'cancelled')
            ->whereBetween('orders.created_at', [$range->start, $range->end])
            ->groupBy('order_items.product_id')
            ->selectRaw('order_items.product_id AS product_id, MAX(order_items.name) AS product, SUM(order_items.total_price) AS net_sales, SUM(order_items.quantity * COALESCE(products.cost_price, 0)) AS cogs, SUM(order_items.quantity) AS units')
            ->get()
            ->map(function ($row) {
                $net = (float) $row->net_sales;
                $cogs = (float) $row->cogs;
                $gp = round($net - $cogs, 2);
                $margin = $net > 0 ? round(($gp / $net) * 100, 2) : null;

                return [
                    'product_id' => (int) $row->product_id,
                    'product' => (string) $row->product,
                    'units_sold' => (int) $row->units,
                    'net_sales' => round($net, 2),
                    'cogs' => round($cogs, 2),
                    'gross_profit' => $gp,
                    'gross_profit_margin' => $margin,
                ];
            })
            ->sortByDesc('gross_profit')
            ->values()
            ->all();

        return $rows;
    }

    /** Category-level margin & trend. */
    public static function categoryProfitability(DateRange $range): array
    {
        return DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->whereNot('orders.status', 'cancelled')
            ->whereBetween('orders.created_at', [$range->start, $range->end])
            ->groupBy('categories.name')
            ->selectRaw('categories.name AS category, SUM(order_items.total_price) AS revenue, SUM(order_items.quantity * COALESCE(products.cost_price, 0)) AS cogs, SUM(order_items.quantity) AS units')
            ->orderBy('revenue', 'desc')
            ->get()
            ->map(fn ($row) => [
                'category' => (string) $row->category,
                'units' => (int) $row->units,
                'revenue' => round((float) $row->revenue, 2),
                'cogs' => round((float) $row->cogs, 2),
                'gross_profit' => round((float) $row->revenue - (float) $row->cogs, 2),
                'gross_profit_margin' => (float) $row->revenue > 0 ? round(((float) $row->revenue - (float) $row->cogs) / (float) $row->revenue * 100, 2) : null,
            ])
            ->all();
    }

    /** Discounts that materially reduce profitability, by product. */
    public static function discountErosion(DateRange $range): array
    {
        return DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->whereNot('orders.status', 'cancelled')
            ->whereBetween('orders.created_at', [$range->start, $range->end])
            ->where('orders.discount_amount', '>', 0)
            ->groupBy('order_items.product_id')
            ->selectRaw('MAX(order_items.name) AS product,
                SUM(order_items.total_price) AS net_sales,
                SUM(order_items.quantity) AS units,
                SUM(order_items.total_price / NULLIF(orders.subtotal, 0) * orders.discount_amount) AS discount_applied,
                SUM(order_items.quantity * COALESCE(products.cost_price, 0)) AS cogs')
            ->get()
            ->map(fn ($row) => [
                'product' => (string) $row->product,
                'units_sold' => (int) $row->units,
                'net_sales' => round((float) $row->net_sales, 2),
                'discount_applied' => round((float) $row->discount_applied, 2),
                'gross_profit' => round((float) $row->net_sales - (float) $row->cogs, 2),
                'margin_percent' => (float) $row->net_sales > 0 ? round((((float) $row->net_sales - (float) $row->cogs) / (float) $row->net_sales) * 100, 2) : null,
            ])
            ->sortByDesc('discount_applied')
            ->values()
            ->all();
    }
}
