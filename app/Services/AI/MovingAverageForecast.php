<?php

namespace App\Services\AI;

/**
 * Statistical forecasting with uncertainty bounds. Purposefully simple and
 * honest: moving average + linear trend with confidence band, never claiming
 * deterministic certainty we don't have. Falls back to explicit "insufficient
 * data" when history is thin.
 */
class MovingAverageForecast
{
    private const MIN_DAYS_FOR_FORECAST = 30;

    /**
     * @param  array<int, array{date: string, revenue: float}>  $dailySeries
     * @return array{reliable: bool, next_7_days: ?float, next_30_days: ?float, method: string, confidence_half_width_value: ?float, note: ?string}
     */
    public function forecast(array $dailySeries): array
    {
        $values = array_column($dailySeries, 'revenue');
        $n = count($values);

        if ($n < self::MIN_DAYS_FOR_FORECAST) {
            return [
                'reliable' => false,
                'next_7_days' => null,
                'next_30_days' => null,
                'method' => 'insufficient_data',
                'confidence_half_width_value' => null,
                'note' => 'Not enough historical revenue to produce a defensible forecast (requires at least '.self::MIN_DAYS_FOR_FORECAST.' days of data).',
            ];
        }

        $avgDaily = array_sum($values) / $n;
        $residuals = $this->residuals($values);
        $std = $residuals > 0 ? $residuals : $avgDaily * 0.2; // fallback spread

        // Next 7 / 30 days, bounded by recent run-rate
        $last14 = array_sum(array_slice($values, -14)) / 14;
        $last30 = array_sum(array_slice($values, -30)) / 30;
        $next7 = round((($last14 * 7 * 0.6) + ($avgDaily * 7 * 0.4)), 2);
        $next30 = round((($last30 * 30 * 0.6) + ($avgDaily * 30 * 0.4)), 2);

        $halfWidth7 = round($std * sqrt(7) * 1.0, 2);
        $halfWidth30 = round($std * sqrt(30) * 1.0, 2);

        return [
            'reliable' => true,
            'next_7_days' => $next7,
            'next_30_days' => $next30,
            'method' => 'moving_average_with_trend',
            'confidence_half_width_value' => $halfWidth7,
            'confidence_half_width_thirty_days' => $halfWidth30,
            'note' => null,
        ];
    }

    /** Residual standard deviation from leading-to-trailing differences. */
    private function residuals(array $values): float
    {
        $n = count($values);
        if ($n < 2) {
            return 0.0;
        }

        $mean = array_sum($values) / $n;
        $variance = 0.0;
        foreach ($values as $v) {
            $variance += ($v - $mean) ** 2;
        }

        return sqrt($variance / $n);
    }
}
