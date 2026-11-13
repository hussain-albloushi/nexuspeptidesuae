<?php

namespace App\Services\AI\Tools;

use App\Services\AI\CustomerAnalytics;
use App\Services\AI\DateRange;
use App\Services\AI\InventoryAnalytics;
use App\Services\AI\ProductAnalytics;
use App\Services\AI\ProfitAnalytics;
use App\Services\AI\SalesAnalytics;

class ToolExecutor
{
    private const MAX_RANGE_DAYS = 366 * 5;

    /**
     * Validate a tool name and parameters against the registry and date limits.
     */
    public static function validate(string $name, array $params): array
    {
        if (! array_key_exists($name, ToolRegistry::DEFINITIONS)) {
            return [false, 'Unknown tool requested: '.$name];
        }

        $schema = ToolRegistry::DEFINITIONS[$name]['input_schema'];

        foreach ($schema['required'] ?? [] as $required) {
            if (! array_key_exists($required, $params)) {
                return [false, "Missing required parameter: {$required}"];
            }
        }

        foreach ($params as $key => $value) {
            if (! array_key_exists($key, $schema['properties'] ?? [])) {
                return [false, "Parameter not allowed: {$key}"];
            }

            $prop = $schema['properties'][$key];
            $type = $prop['type'] ?? 'string';

            $valid = match ($type) {
                'string' => is_string($value),
                'integer' => is_int($value) || (is_string($value) && ctype_digit($value)),
                'number', 'numeric' => is_numeric($value),
                'boolean' => is_bool($value),
                'array' => is_array($value),
                default => false,
            };

            if (! $valid) {
                return [false, "Invalid type for {$key}: expected {$type}"];
            }

            if ($type === 'integer' && isset($prop['minimum']) && (int) $value < (int) $prop['minimum']) {
                return [false, "{$key} must be >= {$prop['minimum']}"];
            }
            if ($type === 'integer' && isset($prop['maximum']) && (int) $value > (int) $prop['maximum']) {
                return [false, "{$key} must be <= {$prop['maximum']}"];
            }
        }

        // Every date parameter in the payload must form a sane, capped range.
        $pairs = self::datePairs($name, $params);
        foreach ($pairs as [$start, $end]) {
            $rangeError = self::checkRange($start, $end);
            if ($rangeError !== null) {
                return [false, $rangeError];
            }
        }

        return [true, null];
    }

    /** @return array<int, array{0: string, 1: string}> */
    private static function datePairs(string $name, array $params): array
    {
        if ($name === 'compareSalesPeriods') {
            return [
                [(string) ($params['start_a'] ?? ''), (string) ($params['end_a'] ?? '')],
                [(string) ($params['start_b'] ?? ''), (string) ($params['end_b'] ?? '')],
            ];
        }

        return [[(string) ($params['start'] ?? ''), (string) ($params['end'] ?? '')]];
    }

    private static function checkRange(string $start, string $end): ?string
    {
        try {
            $s = new \DateTime($start);
            $e = new \DateTime($end);
        } catch (\Throwable) {
            return 'Invalid date value provided.';
        }

        if ($s > $e) {
            return 'start must not be after end';
        }

        $days = (int) $s->diff($e)->days;

        if ($days > self::MAX_RANGE_DAYS) {
            return 'Date range exceeds 5 years';
        }

        return null;
    }

    /** Execute a validated tool call. */
    public static function execute(string $name, array $params): array
    {
        return match ($name) {
            'getSalesSummary' => self::withSingleRange($name, $params, fn (DateRange $range): array => [
                'revenue' => round(SalesAnalytics::revenue($range), 2),
                'merchandise_revenue' => round(SalesAnalytics::merchandiseRevenue($range), 2),
                'discounts' => round(SalesAnalytics::discounts($range), 2),
                'units_sold' => SalesAnalytics::unitsSold($range),
                'orders' => SalesAnalytics::ordersCount($range),
                'aov' => SalesAnalytics::averageOrderValue($range),
                'refunds' => SalesAnalytics::refunds($range),
            ]),
            'getTopSellingProducts' => self::withSingleRange($name, $params, fn (DateRange $range): array => self::limit(self::byProduct($range), (int) ($params['limit'] ?? 10))),
            'getProductProfitability' => self::withSingleRange($name, $params, fn (DateRange $range): array => self::limit(ProfitAnalytics::productProfitability($range), (int) ($params['limit'] ?? 10))),
            'getSlowMovingProducts' => self::withSingleRange($name, $params, fn (DateRange $range): array => self::limit(self::slowMoving($range), (int) ($params['limit'] ?? 15))),
            'getInventoryAlerts' => self::withSingleRange($name, $params, fn (DateRange $range): array => InventoryAnalytics::alerts($range)),
            'compareSalesPeriods' => (function () use ($name, $params): array {
                $rangeA = DateRange::from((string) $params['start_a'], (string) $params['end_a']);
                $rangeB = DateRange::from((string) $params['start_b'], (string) $params['end_b']);

                return ['tool' => $name, 'result' => [
                    'period_a' => self::summary($rangeA),
                    'period_b' => self::summary($rangeB),
                    'delta' => [
                        'revenue_delta' => round(SalesAnalytics::revenue($rangeA) - SalesAnalytics::revenue($rangeB), 2),
                        'orders_delta' => SalesAnalytics::ordersCount($rangeA) - SalesAnalytics::ordersCount($rangeB),
                    ],
                ]];
            })(),
            'getCategoryPerformance' => self::withSingleRange($name, $params, fn (DateRange $range): array => SalesAnalytics::byCategory($range)),
            'getProfitSummary' => self::withSingleRange($name, $params, fn (DateRange $range): array => ProfitAnalytics::pAndL($range)),
            'getCustomerInsights' => self::withSingleRange($name, $params, fn (DateRange $range): array => self::compact(CustomerAnalytics::insights($range), 20)),
            default => ['tool' => $name, 'error' => 'Unhandled tool'],
        };
    }

    /** @param callable(DateRange): array<string, mixed> $callback */
    private static function withSingleRange(string $name, array $params, callable $callback): array
    {
        return ['tool' => $name, 'result' => $callback(DateRange::from((string) $params['start'], (string) $params['end']))];
    }

    private static function byProduct(DateRange $range): array
    {
        $rows = SalesAnalytics::byProduct($range);

        return array_map(fn ($r) => [
            'product' => $r['product'],
            'units_sold' => $r['units'],
            'revenue' => $r['revenue'],
            'gross_profit' => $r['gross_profit'],
            'margin_percent' => $r['margin_percent'],
        ], $rows);
    }

    private static function slowMoving(DateRange $range): array
    {
        $rows = ProductAnalytics::byRange($range);

        return array_values(array_filter($rows, fn ($p) => ($p['units_sold'] ?? 0) === 0 || ($p['units_sold'] ?? 0) <= 2));
    }

    private static function summary(DateRange $range): array
    {
        return [
            'orders' => SalesAnalytics::ordersCount($range),
            'revenue' => round(SalesAnalytics::revenue($range), 2),
            'aov' => SalesAnalytics::averageOrderValue($range),
        ];
    }

    private static function limit(array $items, int $n): array
    {
        return array_slice($items, 0, max(1, min((int) $n, 50)));
    }

    /** Trim massive arrays for the LLM context window. */
    private static function compact(array $array, int $topN): array
    {
        $compact = $array;

        if (isset($compact['top_customers'])) {
            $compact['top_customers'] = array_slice($compact['top_customers'], 0, $topN);
        }

        return $compact;
    }
}
