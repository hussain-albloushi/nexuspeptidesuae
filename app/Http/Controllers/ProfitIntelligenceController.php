<?php

namespace App\Http\Controllers;

use App\Services\AI\AiPeriod;
use App\Services\AI\AiSummary;
use App\Services\AI\ProfitAnalytics;
use App\Services\AI\SalesAnalytics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfitIntelligenceController extends Controller
{
    public function pnl(Request $request): JsonResponse
    {
        $range = AiPeriod::fromRequest($request);
        $pnl = ProfitAnalytics::pAndL($range);
        $previous = $range->previous();
        $prevPnl = ProfitAnalytics::pAndL($previous);

        $analytics = [
            'gross_profit' => $this->compareArray($pnl['gross_profit'], $prevPnl['gross_profit'] ?? null),
            'gross_margin_percent' => $pnl['gross_profit_margin'],
            'net_sales_ex_vat' => $pnl['net_sales_ex_vat'],
            'refunds' => $pnl['refunds'],
            'expense_data_complete' => $pnl['expense_data_complete'],
        ];
        $summary = AiSummary::summarize($analytics, 'Explain profitability this period in business terms. If expense data is incomplete, say so explicitly. Recommend one margin action.');

        return response()->json([
            'period' => AiPeriod::toResponse($range),
            'comparison_period' => AiPeriod::toResponse($previous),
            'pnl' => $pnl,
            'previous_period' => $prevPnl,
            'summary' => $summary['text'],
            'summary_source' => $summary['source'],
            'analysis_generated_at' => $summary['generated_at'],
        ]);
    }

    public function products(Request $request): JsonResponse
    {
        $range = AiPeriod::fromRequest($request);

        return response()->json([
            'period' => AiPeriod::toResponse($range),
            'products' => ProfitAnalytics::productProfitability($range),
        ]);
    }

    public function categories(Request $request): JsonResponse
    {
        $range = AiPeriod::fromRequest($request);

        return response()->json([
            'period' => AiPeriod::toResponse($range),
            'categories' => ProfitAnalytics::categoryProfitability($range),
        ]);
    }

    public function discountsImpact(Request $request): JsonResponse
    {
        $range = AiPeriod::fromRequest($request);
        $pnl = ProfitAnalytics::pAndL($range);

        $gross = max($pnl['gross_revenue'], 0.01);

        return response()->json([
            'period' => AiPeriod::toResponse($range),
            'total_discounts' => $pnl['discounts'],
            'discount_share_of_gross_percent' => round(($pnl['discounts'] / $gross) * 100, 2),
            'by_product' => ProfitAnalytics::discountErosion($range),
        ]);
    }

    /** @return array<string, mixed> */
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
}
