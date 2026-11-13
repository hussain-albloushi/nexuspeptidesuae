<?php

namespace App\Http\Controllers;

use App\Models\AiRecommendation;
use App\Models\Setting;
use App\Services\AI\AiPeriod;
use App\Services\AI\RecommendationEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiRecommendationsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'in:'.implode(',', AiRecommendation::CATEGORIES)],
            'priority' => ['nullable', 'string', 'in:'.implode(',', AiRecommendation::PRIORITIES)],
            'status' => ['nullable', 'string', 'in:'.implode(',', AiRecommendation::STATUSES)],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        $query = AiRecommendation::query();

        if ($search = trim((string) $request->input('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%')
                    ->orWhere('reasoning', 'like', '%'.$search.'%');
            });
        }

        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }
        if ($priority = $request->input('priority')) {
            $query->where('priority', $priority);
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $page = max(1, (int) $request->input('page', 1));
        $perPage = min(50, max(5, (int) $request->input('per_page', 15)));
        $total = $query->count();

        $items = $query->orderByRaw("CASE priority WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
            ->orderByDesc('created_at')
            ->forPage($page, $perPage)
            ->get();

        return response()->json([
            'data' => $items->map(fn ($r) => $this->toArray($r))->all(),
            'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => $total],
            'categories' => AiRecommendation::CATEGORIES,
            'priorities' => AiRecommendation::PRIORITIES,
            'statuses' => AiRecommendation::STATUSES,
            'counts' => AiRecommendation::selectRaw('status, COUNT(*) AS count')->groupBy('status')->pluck('count', 'status')->all(),
        ]);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', AiRecommendation::STATUSES)],
        ]);

        $recommendation = AiRecommendation::findOrFail($id);
        $recommendation->status = $data['status'];
        if ($data['status'] === 'completed' && $recommendation->implemented_at === null) {
            $recommendation->implemented_at = now();
        }
        if ($data['status'] !== 'completed') {
            $recommendation->implemented_at = null;
        }
        $recommendation->save();

        return response()->json(['recommendation' => $this->toArray($recommendation)]);
    }

    public function updateNote(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'internal_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $recommendation = AiRecommendation::findOrFail($id);
        $recommendation->internal_notes = $data['internal_notes'];
        $recommendation->save();

        return response()->json(['recommendation' => $this->toArray($recommendation)]);
    }

    public function regenerate(Request $request): JsonResponse
    {
        $range = AiPeriod::fromRequest($request);
        $thresholds = $this->thresholds();

        $created = (new RecommendationEngine)->generate($range, $thresholds);

        return response()->json([
            'message' => count($created) > 0
                ? sprintf('%d new recommendation(s) generated.', count($created))
                : 'No new recommendations — everything already identified is up to date.',
            'created' => count($created),
            'recommendations' => collect($created)->map(fn ($r) => $this->toArray($r))->all(),
        ]);
    }

    /** @return array<string, mixed> */
    private function toArray(AiRecommendation $r): array
    {
        return [
            'id' => (int) $r->id,
            'title' => (string) $r->title,
            'description' => (string) $r->description,
            'category' => (string) $r->category,
            'priority' => (string) $r->priority,
            'status' => (string) $r->status,
            'reasoning' => (string) $r->reasoning,
            'suggested_action' => (string) $r->suggested_action,
            'expected_impact' => (string) $r->expected_impact,
            'supporting_data' => json_decode((string) $r->supporting_data, true) ?? [],
            'relevant_products' => $r->relevant_products ?? [],
            'relevant_categories' => $r->relevant_categories ?? [],
            'internal_notes' => $r->internal_notes,
            'implemented_at' => $r->implemented_at?->toIso8601String(),
            'created_at' => $r->created_at?->toIso8601String(),
        ];
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
