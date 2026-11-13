<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Setting;
use App\Services\AI\AiPeriod;
use App\Services\AI\DateRange;
use App\Services\AI\ProductAnalytics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductIntelligenceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $range = AiPeriod::fromRequest($request);
        $thresholds = $this->thresholds();

        $products = ProductAnalytics::byRange($range);
        $classification = ProductAnalytics::classify($products, $thresholds);

        // Attach classes to each product row
        $classOf = [];
        foreach ($classification as $class => $ids) {
            foreach ($ids as $id) {
                $classOf[$id][] = $class;
            }
        }
        foreach ($products as &$p) {
            $p['classes'] = $classOf[$p['id']] ?? [];
        }
        unset($p);

        // Search & filters
        $search = trim((string) $request->input('search', ''));
        $category = trim((string) $request->input('category', ''));
        $class = trim((string) $request->input('class', ''));

        $filtered = array_values(array_filter($products, function ($p) use ($search, $category, $class) {
            if ($search !== '') {
                $hay = mb_strtolower($p['name'].' '.$p['sku'].' '.$p['category']);
                if (! str_contains($hay, mb_strtolower($search))) {
                    return false;
                }
            }
            if ($category !== '' && mb_strtolower($p['category']) !== mb_strtolower($category)) {
                return false;
            }
            if ($class !== '' && ! in_array($class, $p['classes'], true)) {
                return false;
            }

            return true;
        }));

        usort($filtered, fn ($a, $b) => ($b['revenue'] ?? 0) <=> ($a['revenue'] ?? 0));

        $page = max(1, (int) $request->input('page', 1));
        $perPage = min(100, max(10, (int) $request->input('per_page', 25)));
        $total = count($filtered);
        $pageItems = array_slice($filtered, ($page - 1) * $perPage, $perPage);

        return response()->json([
            'period' => AiPeriod::toResponse($range),
            'thresholds' => $thresholds,
            'data' => $pageItems,
            'classification' => $classification,
            'summary' => [
                'total_products' => count($products),
                'active' => count(array_filter($products, fn ($p) => $p['is_active'])),
                'no_sales' => count($classification['no_sales']),
                'restock_needed' => count($classification['restock_needed']),
                'low_profit' => count($classification['low_profit']),
                'high_profit' => count($classification['high_profit']),
            ],
            'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => $total],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $product = Product::with('category')->findOrFail($id);
        $range = AiPeriod::fromRequest($request);
        $thresholds = $this->thresholds();

        $metrics = collect(ProductAnalytics::byRange($range))->firstWhere('id', $product->id)
            ?? [
                'id' => (int) $product->id,
                'name' => (string) $product->name,
                'sku' => (string) ($product->sku ?? ''),
                'category' => (string) ($product->category?->name ?? '—'),
                'price' => round((float) $product->price, 2),
                'cost_price' => round((float) $product->cost_price, 2),
                'units_sold' => 0,
                'revenue' => 0,
                'gross_profit' => 0,
                'gross_profit_margin' => null,
                'stock_quantity' => (int) ($product->stock_quantity ?? 0),
                'low_stock_threshold' => (int) ($product->low_stock_threshold ?? 5),
                'is_active' => (bool) $product->is_active,
                'order_count' => 0,
                'discount_total' => 0,
            ];

        // Prior period for momentum
        $previous = $range->previous();
        $prevMetrics = collect(ProductAnalytics::byRange($previous))->firstWhere('id', $product->id);

        $momentum = null;
        if ($prevMetrics && $prevMetrics['revenue'] > 0) {
            $momentum = round((($metrics['revenue'] - $prevMetrics['revenue']) / max($prevMetrics['revenue'], 0.01)) * 100, 2);
        } elseif ($prevMetrics && $metrics['revenue'] > 0) {
            $momentum = 100.0;
        }

        // Classification for this product
        $classification = ProductAnalytics::classify([$metrics], $thresholds);
        $classes = [];
        foreach ($classification as $class => $ids) {
            if (in_array($product->id, $ids, true)) {
                $classes[] = $class;
            }
        }

        // Returns impact — approved return requests for this product's orders in range
        $returns = (int) DB::table('return_requests')
            ->join('orders', 'return_requests.order_id', '=', 'orders.id')
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->where('order_items.product_id', $product->id)
            ->whereBetween('return_requests.created_at', [$range->start, $range->end])
            ->count();

        return response()->json([
            'period' => AiPeriod::toResponse($range),
            'product' => array_merge($metrics, [
                'classes' => $classes,
                'momentum_percent' => $momentum,
                'returns_in_period' => $returns,
                'low_stock_threshold' => $metrics['low_stock_threshold'],
            ]),
            'previous_period' => $prevMetrics,
        ]);
    }

    public function compare(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:2', 'max:6'],
            'ids.*' => ['integer', 'exists:products,id'],
            'start' => ['nullable', 'date'],
            'end' => ['nullable', 'date', 'after_or_equal:start'],
        ]);

        $range = ! empty($data['start'])
            ? DateRange::from((string) $data['start'], (string) $data['end'])
            : AiPeriod::fromRequest($request);

        $products = collect(ProductAnalytics::byRange($range))->keyBy('id');
        $items = [];
        foreach ($data['ids'] as $id) {
            $p = $products->get((int) $id);
            if ($p) {
                $items[] = $p;
            }
        }

        return response()->json([
            'period' => AiPeriod::toResponse($range),
            'comparison' => $items,
        ]);
    }

    /** @return array<string, float|int> */
    private function thresholds(): array
    {
        $get = fn (string $key, $default) => Setting::where('key', $key)->value('value') ?? $default;

        return [
            'ai_profit_margin_warning_threshold' => (float) $get('ai_profit_margin_warning_threshold', 15),
            'ai_min_product_sales_threshold' => (int) $get('ai_min_product_sales_threshold', 5),
            'ai_low_stock_threshold' => (int) $get('ai_low_stock_threshold', 10),
            'ai_sales_decline_warning_threshold' => (float) $get('ai_sales_decline_warning_threshold', -20),
        ];
    }
}
