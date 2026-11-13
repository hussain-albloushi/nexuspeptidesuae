<?php

namespace App\Services\AI\Tools;

class ToolRegistry
{
    /** @var array<string, array<string, mixed>> */
    public const DEFINITIONS = [
        'getSalesSummary' => [
            'name' => 'getSalesSummary',
            'description' => 'Return revenue, orders, AOV, units sold and discounts for a date range.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'start' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                    'end' => ['type' => 'string'],
                ],
                'required' => ['start', 'end'],
                'additionalProperties' => false,
            ],
        ],
        'getTopSellingProducts' => [
            'name' => 'getTopSellingProducts',
            'description' => 'Return the top selling products by revenue in a date range.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'start' => ['type' => 'string'],
                    'end' => ['type' => 'string'],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 25],
                ],
                'required' => ['start', 'end'],
                'additionalProperties' => false,
            ],
        ],
        'getProductProfitability' => [
            'name' => 'getProductProfitability',
            'description' => 'Return units sold, net sales, COGS, gross profit and margin per product for a date range.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'start' => ['type' => 'string'],
                    'end' => ['type' => 'string'],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 25],
                ],
                'required' => ['start', 'end'],
                'additionalProperties' => false,
            ],
        ],
        'getSlowMovingProducts' => [
            'name' => 'getSlowMovingProducts',
            'description' => 'Return products with low or no sales but positive stock (slow-moving).',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'start' => ['type' => 'string'],
                    'end' => ['type' => 'string'],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
                ],
                'required' => ['start', 'end'],
                'additionalProperties' => false,
            ],
        ],
        'getInventoryAlerts' => [
            'name' => 'getInventoryAlerts',
            'description' => 'Return low-stock and out-of-stock items plus restock candidates.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'start' => ['type' => 'string'],
                    'end' => ['type' => 'string'],
                ],
                'required' => ['start', 'end'],
                'additionalProperties' => false,
            ],
        ],
        'compareSalesPeriods' => [
            'name' => 'compareSalesPeriods',
            'description' => 'Compare two date ranges on revenue, orders and AOV.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'start_a' => ['type' => 'string'],
                    'end_a' => ['type' => 'string'],
                    'start_b' => ['type' => 'string'],
                    'end_b' => ['type' => 'string'],
                ],
                'required' => ['start_a', 'end_a', 'start_b', 'end_b'],
                'additionalProperties' => false,
            ],
        ],
        'getCategoryPerformance' => [
            'name' => 'getCategoryPerformance',
            'description' => 'Return revenue, units and margins grouped by category for a date range.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'start' => ['type' => 'string'],
                    'end' => ['type' => 'string'],
                ],
                'required' => ['start', 'end'],
                'additionalProperties' => false,
            ],
        ],
        'getProfitSummary' => [
            'name' => 'getProfitSummary',
            'description' => 'Return gross profit, COGS, discounts, refunds, expenses and net profit for a date range.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'start' => ['type' => 'string'],
                    'end' => ['type' => 'string'],
                ],
                'required' => ['start', 'end'],
                'additionalProperties' => false,
            ],
        ],
        'getCustomerInsights' => [
            'name' => 'getCustomerInsights',
            'description' => 'Return customer insights: repeat rate, AOV, new vs returning, top customers.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'start' => ['type' => 'string'],
                    'end' => ['type' => 'string'],
                ],
                'required' => ['start', 'end'],
                'additionalProperties' => false,
            ],
        ],
    ];

    public static function all(): array
    {
        return array_map(fn ($d) => $d, self::DEFINITIONS);
    }

    /**
     * Definitions in OpenAI Chat Completions "tools" format.
     *
     * The result must be a JSON array, not an object: array_map() preserves the
     * registry's string keys, which would serialise as {"getSalesSummary":...}
     * and providers reject that with '"tools" must be an array'.
     */
    public static function openAiTools(): array
    {
        return array_values(array_map(fn (array $def): array => [
            'type' => 'function',
            'function' => [
                'name' => $def['name'],
                'description' => $def['description'],
                'parameters' => $def['input_schema'],
            ],
        ], self::DEFINITIONS));
    }

    public static function names(): array
    {
        return array_keys(self::DEFINITIONS);
    }
}
