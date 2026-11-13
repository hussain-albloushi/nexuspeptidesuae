<?php

namespace App\Services\AI;

use App\Models\Order;

/**
 * Statistical trend detection used to flag unusual revenue movements and
 * seasonal patterns. Follows the project's "no false precision" approach:
 * results are labelled estimates with an explained method, not predictions.
 */
class TrendDetection
{
    /**
     * Simple linear trend slope over a daily series.
     * Returns slope (revenue per day) and R². Positive slope = upward trend.
     */
    public static function linearTrendSlope(array $series): array
    {
        $n = count($series);
        if ($n < 2) {
            return ['slope' => 0.0, 'r2' => 0.0];
        }

        $xMean = ($n - 1) / 2;
        $yMean = array_sum($series) / $n;

        $num = 0.0;
        $den = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $dx = $i - $xMean;
            $num += $dx * ($series[$i] - $yMean);
            $den += $dx * $dx;
        }

        if ($den === 0.0) {
            return ['slope' => 0.0, 'r2' => 0.0];
        }

        $slope = $num / $den;

        // R²
        $residuals = 0.0;
        $total = 0.0;
        foreach ($series as $i => $y) {
            $pred = $slope * $i + (($yMean) - $slope * $xMean);
            $residuals += ($y - $pred) ** 2;
            $total += ($y - $yMean) ** 2;
        }

        return [
            'slope' => round($slope, 4),
            'r2' => $total > 0 ? round(1 - ($residuals / $total), 4) : 0.0,
        ];
    }

    /**
     * Week-over-week / month-over-month anomaly: compare the most recent window
     * against the mean and standard deviation of prior windows. Values beyond
     * 2σ are flagged as anomalies.
     */
    public static function detectAnomalies(array $series, int $window = 7): array
    {
        if (count($series) < $window * 2) {
            return ['anomalies' => [], 'note' => 'Not enough history to flag anomalies reliably.'];
        }

        $slices = array_chunk($series, $window);
        $means = array_map(fn ($slice) => array_sum($slice) / count($slice), $slices);
        $target = array_pop($means); // most recent full window
        $historyMean = count($means) ? array_sum($means) / count($means) : 0.0;
        $historyStd = self::stdDev($means);

        $anomalies = [];
        if ($historyStd > 0) {
            $z = ($target - $historyMean) / $historyStd;
            if (abs($z) >= 2.0) {
                $anomalies[] = [
                    'index' => 'latest_window',
                    'value' => round($target, 2),
                    'z_score' => round($z, 2),
                    'direction' => $z > 0 ? 'spike' : 'drop',
                    'note' => ($z > 0 ? 'Unusually high' : 'Unusually low').' revenue window detected.',
                ];
            }
        }

        return ['anomalies' => $anomalies, 'note' => $anomalies ? null : 'No anomalies detected.'];
    }

    /** Simple seasonal index: average daily/weekly revenue grouped by weekday. */
    public static function seasonalPattern(DateRange $range): array
    {
        $orders = Order::whereNot('status', 'cancelled')
            ->whereBetween('created_at', [$range->start, $range->end])
            ->get(['created_at', 'total_amount']);

        $byWeekday = [];
        foreach ($orders as $o) {
            $day = $o->created_at->format('l');
            $byWeekday[$day]['orders'] = ($byWeekday[$day]['orders'] ?? 0) + 1;
            $byWeekday[$day]['revenue'] = ($byWeekday[$day]['revenue'] ?? 0.0) + (float) $o->total_amount;
        }

        $rows = [];
        foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $day) {
            $rows[] = [
                'weekday' => $day,
                'orders' => (int) ($byWeekday[$day]['orders'] ?? 0),
                'revenue' => round((float) ($byWeekday[$day]['revenue'] ?? 0), 2),
            ];
        }

        usort($rows, fn ($a, $b) => $b['revenue'] <=> $a['revenue']);
        $strongest = $rows[0] ?? null;
        $weakest = end($rows) ?: null;

        return ['by_weekday' => $rows, 'strongest_day' => $strongest, 'weakest_day' => $weakest];
    }

    /** Best/worst performing products and categories by revenue change vs. prior period. */
    public static function contributors(array $currentByProduct, array $previousByProduct): array
    {
        $previous = collect($previousByProduct)->keyBy('product');

        $contributors = [];
        foreach ($currentByProduct as $row) {
            $prev = $previous->get($row['product']);
            $prevRev = $prev ? (float) $prev['revenue'] : 0.0;
            $delta = round(((float) $row['revenue']) - $prevRev, 2);
            $contributors[] = [
                'product' => $row['product'],
                'revenue' => $row['revenue'],
                'previous_revenue' => $prevRev,
                'delta' => $delta,
                'delta_percent' => $prevRev > 0 ? round(($delta / $prevRev) * 100, 2) : null,
            ];
        }

        usort($contributors, fn ($a, $b) => abs((float) $b['delta']) <=> abs((float) $a['delta']));

        return [
            'top_gainers' => array_slice(array_values(array_filter($contributors, fn ($c) => $c['delta'] > 0)), 0, 5),
            'top_losers' => array_slice(array_values(array_filter($contributors, fn ($c) => $c['delta'] < 0)), 0, 5),
        ];
    }

    private static function stdDev(array $values): float
    {
        $n = count($values);
        if ($n < 2) {
            return 0.0;
        }

        $mean = array_sum($values) / $n;
        $sum = array_sum(array_map(fn ($x) => ($x - $mean) ** 2, $values));

        return sqrt($sum / $n);
    }
}
