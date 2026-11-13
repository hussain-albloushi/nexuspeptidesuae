<?php

namespace App\Services\AI;

use App\Models\Setting;
use Illuminate\Http\Request;

/**
 * Resolves the analysis window from a request's query string.
 *
 * Accepted query params:
 *   - period: today|yesterday|7d|30d|90d|this_month|previous_month|this_year
 *   - or start=YYYY-MM-DD&end=YYYY-MM-DD (custom range)
 *
 * Defaults to the configured default analysis period (settings key
 * ai_default_analysis_period), falling back to 30 days.
 */
class AiPeriod
{
    public static function fromRequest(Request $request): DateRange
    {
        if ($request->filled('start') && $request->filled('end')) {
            return DateRange::from((string) $request->input('start'), (string) $request->input('end'));
        }

        $period = (string) $request->input('period', '');
        switch ($period) {
            case 'today':
                return DateRange::today();
            case 'yesterday':
                return DateRange::yesterday();
            case '7d':
                return DateRange::lastDays(7);
            case '30d':
                return DateRange::lastDays(30);
            case '90d':
                return DateRange::lastDays(90);
            case 'this_month':
                return DateRange::thisMonth();
            case 'previous_month':
                return DateRange::previousMonth();
            case 'this_year':
                return DateRange::thisYear();
            default:
                $defaultDays = (int) (Setting::where('key', 'ai_default_analysis_period')->value('value') ?: 30);

                return DateRange::lastDays(max(1, min(365, $defaultDays)));
        }
    }

    /** @return array<string, string> */
    public static function toResponse(DateRange $range): array
    {
        return ['start' => $range->start->toDateString(), 'end' => $range->end->toDateString()];
    }
}
