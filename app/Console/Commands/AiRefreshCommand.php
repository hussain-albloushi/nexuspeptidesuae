<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\AI\DateRange;
use App\Services\AI\RecommendationEngine;
use App\Services\AI\ReportGenerator;
use Illuminate\Console\Command;

/**
 * Recompute analytics outputs and generate fresh recommendations.
 *
 * Scheduled in routes/console.php. Safe to run repeatedly: recommendation
 * insertion dedupes on a stability signature. AI provider calls are avoided
 * here — everything is deterministic unless reports are requested.
 */
class AiRefreshCommand extends Command
{
    protected $signature = 'ai:refresh
        {--recommendations : Generate recommendations for the configured analysis period}
        {--reports : Generate any scheduled reports that are enabled}
        {--report=* : Force-generate specific report types (e.g. --report=daily_report)}';

    protected $description = 'Refresh AI analytics: regenerate recommendations and scheduled reports';

    public function handle(): int
    {
        $doRecommendations = $this->option('recommendations') || ! $this->option('reports') && ! $this->option('report');

        if ($doRecommendations) {
            $this->refreshRecommendations();
        }

        if ($this->option('reports') || $this->option('report')) {
            $this->refreshReports((array) $this->option('report'));
        }

        return self::SUCCESS;
    }

    private function refreshRecommendations(): void
    {
        $days = (int) (Setting::where('key', 'ai_default_analysis_period')->value('value') ?: 30);
        $range = DateRange::lastDays(max(1, min(365, $days)));

        $thresholds = [
            'ai_profit_margin_warning_threshold' => (float) (Setting::where('key', 'ai_profit_margin_warning_threshold')->value('value') ?: 15),
            'ai_min_product_sales_threshold' => (int) (Setting::where('key', 'ai_min_product_sales_threshold')->value('value') ?: 5),
            'ai_low_stock_threshold' => (int) (Setting::where('key', 'ai_low_stock_threshold')->value('value') ?: 10),
            'ai_sales_decline_warning_threshold' => (float) (Setting::where('key', 'ai_sales_decline_warning_threshold')->value('value') ?: -20),
        ];

        $created = (new RecommendationEngine)->generate($range, $thresholds);
        $this->info(sprintf('ai:refresh — %d new recommendation(s) generated for the last %d day(s).', count($created), $days));
    }

    /** @param array<int, string> $forced */
    private function refreshReports(array $forced): void
    {
        $scheduled = [
            'daily_report' => $this->boolSetting('ai_schedule_daily_report', true),
            'weekly_report' => $this->boolSetting('ai_schedule_weekly_report', true),
            'monthly_report' => $this->boolSetting('ai_schedule_monthly_report', true),
        ];

        $types = $forced !== [] ? $forced : array_keys(array_filter($scheduled));

        foreach ($types as $type) {
            if (! in_array($type, ReportGenerator::TYPES, true)) {
                $this->warn("Skipping unknown report type: {$type}");

                continue;
            }
            $range = ReportGenerator::defaultRangeFor($type);
            $report = ReportGenerator::build($type, $range, scheduled: true);
            $this->info(sprintf('ai:refresh — generated %s (id %d) for %s → %s.', $type, $report->id, $report->period_start, $report->period_end));
        }
    }

    private function boolSetting(string $key, bool $default): bool
    {
        $value = Setting::where('key', $key)->value('value');

        return $value === null ? $default : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
