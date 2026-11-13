<?php

namespace App\Services\AI;

use App\Models\AiRecommendation;
use App\Models\AiReport;

/**
 * Assembles report bodies (markdown) strictly from analytics services.
 * Numbers never come from the LLM; narrative is optional and clearly bounded.
 */
class ReportGenerator
{
    public const TYPES = [
        'daily_report', 'weekly_report', 'monthly_report',
        'product_performance', 'sales_performance', 'profitability',
        'inventory', 'business_opportunities',
    ];

    public static function defaultRangeFor(string $type): DateRange
    {
        return match ($type) {
            'daily_report' => DateRange::yesterday(),
            'weekly_report' => DateRange::lastDays(7),
            'monthly_report' => DateRange::previousMonth(),
            default => DateRange::lastDays(30),
        };
    }

    /** Build the full markdown body for a report type. */
    public static function build(string $type, DateRange $range, bool $scheduled = false): AiReport
    {
        $pnl = ProfitAnalytics::pAndL($range);
        $customer = CustomerAnalytics::insights($range);
        $topProducts = collect(SalesAnalytics::byProduct($range))->take(10);
        $topCategories = SalesAnalytics::byCategory($range);
        $prev = $range->previous();
        $prevRevenue = SalesAnalytics::revenue($prev);
        $revenue = SalesAnalytics::revenue($range);
        $deltaPercent = $prevRevenue > 0 ? round((($revenue - $prevRevenue) / $prevRevenue) * 100, 2) : null;

        $body = '# '.self::title($type)."\n";
        $body .= "**Period:** {$range->start->toDateString()} → {$range->end->toDateString()}\n";
        $body .= '**Generated:** '.now()->toDateTimeString()."\n\n";

        $body .= "## Overview\n\n";
        $body .= sprintf("- **Revenue:** AED %s%s\n", number_format($revenue, 2), $deltaPercent !== null ? sprintf(' (%+.1f%% vs prior period)', $deltaPercent) : '');
        $body .= sprintf("- **Orders:** %d\n", SalesAnalytics::ordersCount($range));
        $body .= sprintf("- **Units sold:** %d\n", SalesAnalytics::unitsSold($range));
        $body .= sprintf("- **Average order value:** AED %s\n", number_format(SalesAnalytics::averageOrderValue($range), 2));
        $body .= sprintf("- **Discounts:** AED %s\n", number_format(SalesAnalytics::discounts($range), 2));
        $body .= sprintf("- **Refunds:** AED %s\n", number_format($pnl['refunds'], 2));
        $body .= sprintf("- **Gross profit:** AED %s (%s margin)\n\n", number_format($pnl['gross_profit'], 2), $pnl['gross_profit_margin'] !== null ? $pnl['gross_profit_margin'].'%' : 'n/a');

        switch ($type) {
            case 'product_performance':
                $body .= "## Top Products by Revenue\n\n";
                $body .= self::productTable($topProducts);
                $body .= "\n## Categories\n\n";
                $body .= self::categoryTable($topCategories);
                break;
            case 'profitability':
                $body .= "## Profit & Loss\n\n";
                $body .= sprintf("- **Gross revenue:** AED %s\n", number_format($pnl['gross_revenue'], 2));
                $body .= sprintf("- **Discounts:** − AED %s\n", number_format($pnl['discounts'], 2));
                $body .= sprintf("- **Net sales (ex VAT):** AED %s\n", number_format($pnl['net_sales_ex_vat'], 2));
                $body .= sprintf("- **COGS:** − AED %s\n", number_format($pnl['cost_of_goods_sold'], 2));
                $body .= sprintf("- **Gross profit:** AED %s (%s%%)\n", number_format($pnl['gross_profit'], 2), $pnl['gross_profit_margin'] ?? 'n/a');
                $body .= sprintf("- **Shipping income:** AED %s\n", number_format($pnl['shipping_income'], 2));
                $body .= sprintf("- **Shipping expenses:** − AED %s\n", number_format($pnl['shipping_expenses'], 2));
                $body .= sprintf("- **Recorded other expenses:** − AED %s\n", number_format($pnl['recorded_expenses'], 2));
                if ($pnl['expense_data_complete']) {
                    $body .= sprintf("- **Net operating profit:** AED %s\n\n", number_format((float) $pnl['net_operating_profit'], 2));
                } else {
                    $body .= "- **Net profit:** *not shown — expense data incomplete.* Gross profit (after COGS) is the defensible figure.\n\n";
                }
                $body .= "## Discount Erosion (top 10)\n\n";
                foreach (array_slice(ProfitAnalytics::discountErosion($range), 0, 10) as $row) {
                    $body .= sprintf("- %s — AED %s discounts on AED %s sales (%s margin)\n", $row['product'], number_format($row['discount_applied'], 2), number_format($row['net_sales'], 2), $row['margin_percent'] ?? 'n/a');
                }
                $body .= "\n";
                break;
            case 'inventory':
                $alerts = InventoryAnalytics::alerts($range);
                $body .= "## Stock Alerts\n\n";
                if ($alerts === []) {
                    $body .= "No stock alerts — all active products are above their thresholds.\n\n";
                } else {
                    foreach ($alerts as $a) {
                        $body .= sprintf("- **%s** (SKU %s) — %s, %d on hand\n", $a['name'], $a['sku'], $a['state'] === 'out_of_stock' ? 'OUT OF STOCK' : 'low stock', $a['stock_quantity']);
                    }
                    $body .= "\n";
                }
                $body .= sprintf("**Inventory value (cost):** AED %s\n\n", number_format(InventoryAnalytics::valuation(), 2));
                break;
            case 'business_opportunities':
                $body .= "## Identified Opportunities\n\n";
                $opportunities = self::opportunities($range);
                if ($opportunities === []) {
                    $body .= "No new opportunities met the recommendation thresholds in this period.\n\n";
                } else {
                    foreach ($opportunities as $o) {
                        $body .= sprintf("### %s\n**Priority:** %s · **Category:** %s\n\n%s\n\n**Suggested action:** %s\n\n", $o['title'], strtoupper($o['priority']), $o['category'], $o['description'], $o['suggested_action']);
                    }
                }
                break;
            case 'sales_performance':
                $body .= "## Revenue by Category\n\n";
                $body .= self::categoryTable($topCategories);
                $body .= "\n## Customer Signals\n\n";
                $body .= sprintf("- Active customers: %d\n", $customer['active_customers']);
                $body .= sprintf("- New customers: %d\n", $customer['new_customers']);
                $body .= sprintf("- Repeat purchase rate: %s%%\n", $customer['repeat_rate'] ?? 'n/a');
                $body .= sprintf("- Revenue per active customer: AED %s\n\n", number_format($customer['revenue_per_active_customer'], 2));
                break;
            case 'daily_report':
            case 'weekly_report':
            case 'monthly_report':
            default:
                $body .= "## Top Products by Revenue\n\n";
                $body .= self::productTable($topProducts);
                $body .= "\n## Categories\n\n";
                $body .= self::categoryTable($topCategories);
                $body .= "\n## Problems & Signals\n\n";
                $lowStock = count(InventoryAnalytics::alerts($range));
                $body .= sprintf("- **Stock alerts:** %d product(s) at/below threshold\n", $lowStock);
                if ($deltaPercent !== null && $deltaPercent < 0) {
                    $body .= sprintf("- **Sales decline:** revenue fell %.1f%% vs the prior period\n", abs($deltaPercent));
                }
                if ($pnl['refunds'] > 0) {
                    $body .= sprintf("- **Refunds:** AED %s refunded this period\n", number_format($pnl['refunds'], 2));
                }
                $body .= "\n## AI Recommendations\n\n";
                foreach (self::opportunities($range, 3) as $o) {
                    $body .= sprintf("- **[%s]** %s — %s\n", strtoupper($o['priority']), $o['title'], $o['suggested_action']);
                }
                $body .= "\n";
                break;
        }

        $body .= "---\n*Report generated by NexusPeptides AI Business Assistant from stored records; figures are computed, not AI-estimated.*\n";

        return AiReport::create([
            'type' => $type,
            'period_start' => $range->start->toDateString(),
            'period_end' => $range->end->toDateString(),
            'content' => $body,
            'scheduled' => $scheduled,
        ]);
    }

    private static function title(string $type): string
    {
        return match ($type) {
            'daily_report' => 'Daily Business Report',
            'weekly_report' => 'Weekly Business Report',
            'monthly_report' => 'Monthly Business Report',
            'product_performance' => 'Product Performance Report',
            'sales_performance' => 'Sales Performance Report',
            'profitability' => 'Profitability Report',
            'inventory' => 'Inventory Report',
            'business_opportunities' => 'AI Business Opportunities Report',
            default => 'Business Report',
        };
    }

    private static function productTable($rows): string
    {
        $body = "| Product | Units | Revenue | Gross Profit | Margin |\n|---|---|---|---|---|\n";
        foreach ($rows as $r) {
            $body .= sprintf("| %s | %d | AED %s | AED %s | %s |\n", $r['product'] ?? $r['name'], $r['units_sold'] ?? $r['units'], number_format((float) $r['revenue'], 2), number_format((float) ($r['gross_profit'] ?? 0), 2), isset($r['gross_profit_margin']) && $r['gross_profit_margin'] !== null ? $r['gross_profit_margin'].'%' : ($r['margin_percent'] ?? 'n/a'));
        }

        return $body."\n";
    }

    private static function categoryTable(array $rows): string
    {
        $body = "| Category | Units | Revenue | Gross Profit | Margin |\n|---|---|---|---|---|\n";
        foreach ($rows as $r) {
            $cogs = (float) ($r['cogs'] ?? 0);
            $revenue = (float) $r['revenue'];
            $gp = $revenue - $cogs;
            $margin = $revenue > 0 ? round(($gp / $revenue) * 100, 2).'%' : 'n/a';
            $body .= sprintf("| %s | %d | AED %s | AED %s | %s |\n", $r['category'], $r['units'], number_format($revenue, 2), number_format($gp, 2), $margin);
        }

        return $body."\n";
    }

    /** @return array<int, array<string, mixed>> Reads existing recommendations; never generates new ones as a side effect. */
    private static function opportunities(DateRange $range, int $limit = 6): array
    {
        return AiRecommendation::query()
            ->orderByRaw("CASE priority WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => ['title' => $r->title, 'category' => $r->category, 'priority' => $r->priority, 'description' => $r->description, 'suggested_action' => $r->suggested_action])
            ->all();
    }
}
