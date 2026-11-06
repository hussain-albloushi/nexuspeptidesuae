<?php
namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Expense;
use Illuminate\Http\Request;

class AccountingReportController extends Controller
{
    public function dashboard(Request $r)
    {
        $start = $r->filled('start_date') ? $r->date('start_date') : now()->subMonth()->startOfDay()->toDateString();
        $end = $r->filled('end_date') ? $r->date('end_date') : now()->endOfDay()->toDateString();

        $base = Order::whereNot('status', 'cancelled')
            ->whereDate('created_at', '>=', $start)
            ->whereDate('created_at', '<=', $end);

        $gross = (clone $base)->sum('subtotal') ?? 0;
        $discount = (clone $base)->sum('discount_amount') ?? 0;
        $vat = (clone $base)->sum('tax_amount') ?? 0;
        $net = $gross - $discount;

        $items = OrderItem::whereIn('order_id', (clone $base)->pluck('id'))->get();
        $cogs = 0;
        foreach ($items as $it) {
            $p = Product::find($it->product_id);
            if ($p) $cogs += ((float) ($p->cost_price ?? 0)) * $it->quantity;
        }

        $expenses = Expense::where('date', '>=', $start)->where('date', '<=', $end)->sum('amount') ?? 0;
        $grossProfit = $net - $cogs;
        $estProfit = $grossProfit - $expenses;

        return response()->json([
            'period' => ['start' => (string) $start, 'end' => (string) $end],
            'sales_today' => round(Order::whereDate('created_at', today())->whereNot('status', 'cancelled')->sum('total_amount') ?? 0, 2),
            'sales_this_month' => round(Order::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->whereNot('status', 'cancelled')->sum('total_amount') ?? 0, 2),
            'sales_period' => round($net, 2),
            'net_sales_ex_vat' => round($net, 2),
            'vat_collected' => round($vat, 2),
            'gross_profit' => round($grossProfit, 2),
            'expenses' => round($expenses, 2),
            'estimated_net_profit' => round($estProfit, 2),
            'gross_sales' => round($gross, 2),
            'discounts' => round($discount, 2),
            'co_gs' => round($cogs, 2),
        ]);
    }
}
