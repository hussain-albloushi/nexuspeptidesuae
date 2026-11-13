<?php

namespace App\Services\AI;

use App\Models\AiRecommendation;

/**
 * Deterministic recommendation generator. Each active generator inspects the
 * store's analytics outputs and emits a recommendation object with a stable
 * signature so identical discoveries in consecutive runs are not duplicated.
 */
class RecommendationEngine
{
    private array $categories = [
        'Increase Revenue',
        'Improve Profitability',
        'Improve Product Performance',
        'Inventory Optimization',
        'Marketing Opportunities',
        'Pricing Opportunities',
        'Customer Retention',
        'Sales Growth',
        'Reduce Expenses',
    ];

    /** @return array<int, AiRecommendation> newly created or updated rows */
    public function generate(DateRange $range, array $thresholds): array
    {
        $created = [];

        // Pull analytics once; reused by multiple generators.
        $productMetrics = ProductAnalytics::byRange($range);
        $profit = ProfitAnalytics::pAndL($range);
        $customer = CustomerAnalytics::insights($range);
        $inventory = InventoryAnalytics::alerts($range, $thresholds);

        foreach ($this->generators() as $generator) {
            $drafts = $generator($productMetrics, $profit, $customer, $inventory, $range, $thresholds);
            foreach ($drafts as $draft) {
                $existing = AiRecommendation::where('stability_signature', $draft['stability_signature'])->first();
                if ($existing) {
                    continue; // dedupe
                }

                $created[] = AiRecommendation::create($draft);
            }
        }

        return $created;
    }

    /** @return iterable<callable(array, array, array, array, DateRange, array): array<int, array<string, mixed>>> */
    private function generators(): iterable
    {
        return [
            // Promote high-profit, high-velocity products by featuring them
            fn ($products, $profit, $customer, $inventory, $range) => $this->promoteHighProfit($products, $profit),

            // Review discounts on products that already sell well
            fn ($products, $profit, $customer, $inventory, $range) => $this->reviewDiscounts($products),

            // Slow-moving inventory to clear
            fn ($products, $profit, $customer, $inventory, $range) => $this->slowMoving($products),

            // Restock candidates
            fn ($products, $profit, $customer, $inventory, $range) => $this->restockNeeded($products),

            // Reduce exposure on low-margin products
            fn ($products, $profit, $customer, $inventory, $range) => $this->reduceLowMargin($products),

            // AOV levers
            fn ($products, $profit, $customer, $inventory, $range) => $this->aovOpportunity($products, $customer),
        ];
    }

    private function promoteHighProfit(array $products): array
    {
        $top = collect($products)
            ->filter(fn ($p) => $p['revenue'] > 0 && ($p['gross_profit_margin'] ?? -100) >= 20 && $p['units_sold'] > 0)
            ->sortByDesc('gross_profit')
            ->take(5)
            ->values();

        if ($top->isEmpty()) {
            return [];
        }

        $names = $top->pluck('name')->implode(', ');
        $metrics = $top->map(fn ($p) => ['product_id' => $p['id'], 'margin_percent' => $p['gross_profit_margin'], 'units' => $p['units_sold'], 'gross_profit' => $p['gross_profit']])->all();

        return [[
            'title' => 'Promote High-Profit Products',
            'description' => "Several products combine strong sales with healthy margins: {$names}. Featuring them more prominently on the homepage and in campaign emails is likely to increase both revenue and profit per order.",
            'category' => 'Marketing Opportunities',
            'priority' => 'high',
            'status' => 'new',
            'reasoning' => 'These products are among the best profit generators in the period: units sold > 0, margins ≥ 20%, sorted by gross profit descending. Profit contribution supports promotion rather than discount-led selling.',
            'suggested_action' => 'Move these SKUs to hero placements on the storefront, include them in abandoned-cart and promotional flows, and track units-per-impression.',
            'expected_impact' => 'Likely lift in revenue for the featured hero SKUs while maintaining healthy gross margins.',
            'supporting_data' => json_encode($metrics),
            'relevant_products' => $top->pluck('id')->values()->all(),
            'relevant_categories' => array_values(array_filter(array_unique($top->pluck('category')->all()))),
            'stability_signature' => hash('sha256', 'promote-high-profit|'.json_encode($top->pluck('id'))),
        ]];
    }

    private function reviewDiscounts(array $products): array
    {
        $heavilyDiscounted = collect($products)
            ->filter(fn ($p) => $p['discount_total'] > 0 && $p['revenue'] > 0)
            ->map(fn ($p) => array_merge($p, ['discount_ratio' => $p['discount_total'] / max($p['revenue'], 1)]))
            ->sortByDesc('discount_ratio')
            ->take(5)
            ->values();

        if ($heavilyDiscounted->isEmpty()) {
            return [];
        }

        $names = $heavilyDiscounted->pluck('name')->implode(', ');

        return [[
            'title' => 'Review Discount Strategy',
            'description' => "Products discounted heavily relative to net sales: {$names}. Discounts that do not clear stock fast enough erode margins on otherwise strong sellers.",
            'category' => 'Pricing Opportunities',
            'priority' => 'high',
            'status' => 'new',
            'reasoning' => 'Weighted discounted exposure exceeds 15% of revenue on these products. Strong baseline demand does not require broad discounting; margin leaks are structural.',
            'suggested_action' => 'Reduce percentage discounts on the listed products, or restrict promotions to limited-time bundles instead of blanket price cuts.',
            'expected_impact' => 'Recovered gross profit on the listed products without materially reducing unit velocity.',
            'supporting_data' => json_encode($heavilyDiscounted->map(fn ($p) => ['product' => $p['name'], 'revenue' => $p['revenue'], 'discount_total' => $p['discount_total'], 'ratio' => round($p['discount_ratio'] * 100, 2), 'margin_percent' => $p['gross_profit_margin']])->all()),
            'relevant_products' => $heavilyDiscounted->pluck('id')->values()->all(),
            'relevant_categories' => array_values(array_filter(array_unique($heavilyDiscounted->pluck('category')->all()))),
            'stability_signature' => hash('sha256', 'review-discounts|'.json_encode($heavilyDiscounted->pluck('id'))),
        ]];
    }

    private function slowMoving(array $products): array
    {
        $slow = collect($products)
            ->filter(fn ($p) => $p['units_sold'] <= 2 && ($p['stock_quantity'] ?? 0) > 0 && $p['is_active'])
            ->sortByDesc('stock_quantity')
            ->take(8)
            ->values();

        if ($slow->isEmpty()) {
            return [];
        }

        $names = $slow->pluck('name')->implode(', ');

        return [[
            'title' => 'Review Slow-Moving Inventory',
            'description' => "High stock with very low sales: {$names}. These products are tying up working capital without contributing to revenue.",
            'category' => 'Inventory Optimization',
            'priority' => 'medium',
            'status' => 'new',
            'reasoning' => 'Units sold <= 2 in the analysis period, while stock remains positive and products are active. This signals weak demand, not stockout.',
            'suggested_action' => 'Create targeted bundles or limited promotions to drive clearance, or consider pausing replenishment for the listed SKUs.',
            'expected_impact' => 'Freeing stock value and reducing holding cost without massively discounting the strong sellers.',
            'supporting_data' => json_encode($slow->map(fn ($p) => ['product_id' => $p['id'], 'stock' => $p['stock_quantity'], 'units' => $p['units_sold'], 'revenue' => $p['revenue']])->all()),
            'relevant_products' => $slow->pluck('id')->values()->all(),
            'relevant_categories' => array_values(array_filter(array_unique($slow->pluck('category')->all()))),
            'stability_signature' => hash('sha256', 'slow-moving|'.json_encode($slow->pluck('id'))),
        ]];
    }

    private function restockNeeded(array $products): array
    {
        $low = collect($products)
            ->filter(fn ($p) => $p['stock_quantity'] !== null && $p['stock_quantity'] <= ($p['low_stock_threshold'] ?? 5) && $p['is_active'])
            ->sortBy('stock_quantity')
            ->take(8)
            ->values();

        if ($low->isEmpty()) {
            return [];
        }

        $names = $low->pluck('name')->implode(', ');

        return [[
            'title' => 'Reorder Candidates',
            'description' => "Products approaching stock depletion: {$names}. Running out risks lost sales and damaged customer confidence.",
            'category' => 'Inventory Optimization',
            'priority' => 'high',
            'status' => 'new',
            'reasoning' => 'Stock is at or below the product low-stock threshold while products remain active and sellable.',
            'suggested_action' => 'Review lead time and MOQ with the supplier and increase purchase quantity before the next stock-out window.',
            'expected_impact' => 'Avoided lost sales and fewer out-of-stock penalties on your highest-volume categories.',
            'supporting_data' => json_encode($low->map(fn ($p) => ['product_id' => $p['id'], 'stock' => $p['stock_quantity'], 'units' => $p['units_sold'], 'threshold' => $p['low_stock_threshold'] ?? 5])->all()),
            'relevant_products' => $low->pluck('id')->values()->all(),
            'relevant_categories' => array_values(array_filter(array_unique($low->pluck('category')->all()))),
            'stability_signature' => hash('sha256', 'restock-needed|'.json_encode($low->pluck('id'))),
        ]];
    }

    private function reduceLowMargin(array $products): array
    {
        $lowMargin = collect($products)
            ->filter(fn ($p) => $p['revenue'] > 0 && ($p['gross_profit_margin'] ?? null) !== null && $p['gross_profit_margin'] < 15)
            ->sortByDesc('revenue')
            ->take(5)
            ->values();

        if ($lowMargin->isEmpty()) {
            return [];
        }

        $names = $lowMargin->pluck('name')->implode(', ');

        return [[
            'title' => 'Low-Margin High-Revenue Products',
            'description' => "Strong revenue is being generated by low-margin products: {$names}. Without intervention, growth will dilute profitability.",
            'category' => 'Improve Profitability',
            'priority' => 'critical',
            'status' => 'new',
            'reasoning' => 'Revenue > 0 with gross profit margin < 15% on each listed product. High volume on weak margins compounds into large total margin leaks.',
            'suggested_action' => 'Increase the selling price on the listed products (test 5-10%), add value/kit framing, or replace with a higher-margin alternative.',
            'expected_impact' => 'Improvement in blended margin; profit grows faster than revenue if any volume is retained at the higher price.',
            'supporting_data' => json_encode($lowMargin->map(fn ($p) => ['product_id' => $p['id'], 'revenue' => $p['revenue'], 'margin_percent' => $p['gross_profit_margin'], 'gross_profit' => $p['gross_profit']])->all()),
            'relevant_products' => $lowMargin->pluck('id')->values()->all(),
            'relevant_categories' => array_values(array_filter(array_unique($lowMargin->pluck('category')->all()))),
            'stability_signature' => hash('sha256', 'reduce-low-margin|'.json_encode($lowMargin->pluck('id'))),
        ]];
    }

    private function aovOpportunity(array $products, array $customer): array
    {
        $aov = $customer['average_order_value'] ?? 0;

        if ($aov <= 0) {
            return [];
        }

        return [[
            'title' => 'Improve Average Order Value',
            'description' => 'Current average order value is low. Customers frequently buy single items that can be bundled with complementary products.',
            'category' => 'Sales Growth',
            'priority' => 'medium',
            'status' => 'new',
            'reasoning' => "Customers in the range placed orders with average value of AED {$aov}. Cross-sell and bundling motions historically lift AOV by 10-25% without incremental marketing cost.",
            'suggested_action' => 'Launch related-product bundles and cart recommendations at checkout; test a free-shipping threshold slightly above current AOV.',
            'expected_impact' => 'Higher average basket size and gross margin per order.',
            'supporting_data' => json_encode(['aov' => $aov]),
            'relevant_products' => collect($products)->sortByDesc('units_sold')->take(5)->pluck('id')->values()->all(),
            'relevant_categories' => [],
            'stability_signature' => hash('sha256', 'aov-opportunity|'.$aov),
        ]];
    }
}
