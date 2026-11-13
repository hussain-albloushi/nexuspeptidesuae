<?php

namespace App\Http\Controllers;

use App\Models\AiReport;
use App\Models\Setting;
use App\Services\AI\DateRange;
use App\Services\AI\ReportGenerator;
use App\Services\SiteSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class AiReportsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'type' => ['nullable', 'string', 'in:'.implode(',', ReportGenerator::TYPES)],
        ]);

        $query = AiReport::query()->orderByDesc('created_at');

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        $reports = $query->limit(100)->get()->map(fn (AiReport $r) => [
            'id' => (int) $r->id,
            'type' => (string) $r->type,
            'title' => self::titleFor((string) $r->type),
            'period_start' => (string) $r->period_start,
            'period_end' => (string) $r->period_end,
            'scheduled' => (bool) $r->scheduled,
            'generated_at' => $r->created_at?->toIso8601String(),
            'preview' => mb_substr(strip_tags((string) $r->content), 0, 160),
        ])->all();

        return response()->json([
            'reports' => $reports,
            'types' => array_map(fn ($t) => ['type' => $t, 'title' => self::titleFor($t)], ReportGenerator::TYPES),
            'scheduled' => [
                'daily_report' => self::scheduledEnabled('ai_schedule_daily_report'),
                'weekly_report' => self::scheduledEnabled('ai_schedule_weekly_report'),
                'monthly_report' => self::scheduledEnabled('ai_schedule_monthly_report'),
            ],
        ]);
    }

    public function generate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', Rule::in(ReportGenerator::TYPES)],
            'start' => ['nullable', 'date'],
            'end' => ['nullable', 'date', 'after_or_equal:start'],
        ]);

        $type = (string) $data['type'];
        $range = ! empty($data['start']) && ! empty($data['end'])
            ? DateRange::from((string) $data['start'], (string) $data['end'])
            : ReportGenerator::defaultRangeFor($type);

        $report = ReportGenerator::build($type, $range);

        return response()->json([
            'report' => $this->toArray($report, full: true),
            'message' => 'Report generated.',
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $report = AiReport::findOrFail($id);

        return response()->json(['report' => $this->toArray($report, full: true)]);
    }

    public function destroy(int $id): JsonResponse
    {
        AiReport::findOrFail($id)->delete();

        return response()->json(['message' => 'Report deleted.']);
    }

    public function updateSchedule(Request $request): JsonResponse
    {
        $data = $request->validate([
            'report' => ['required', 'string', Rule::in(['daily_report', 'weekly_report', 'monthly_report'])],
            'enabled' => ['required', 'boolean'],
        ]);

        Setting::updateOrCreate(
            ['key' => 'ai_schedule_'.$data['report']],
            ['value' => $data['enabled'] ? '1' : '0', 'type' => 'string', 'is_secret' => false]
        );
        Cache::forget(SiteSettings::CACHE_KEY);

        return response()->json(['message' => 'Schedule updated.', 'report' => $data['report'], 'enabled' => (bool) $data['enabled']]);
    }

    /** @return array<string, mixed> */
    private function toArray(AiReport $report, bool $full = false): array
    {
        $array = [
            'id' => (int) $report->id,
            'type' => (string) $report->type,
            'title' => self::titleFor((string) $report->type),
            'period_start' => (string) $report->period_start,
            'period_end' => (string) $report->period_end,
            'scheduled' => (bool) $report->scheduled,
            'generated_at' => $report->created_at?->toIso8601String(),
        ];

        if ($full) {
            $array['content'] = (string) $report->content;
        }

        return $array;
    }

    private static function titleFor(string $type): string
    {
        return match ($type) {
            'daily_report' => 'Daily Business Report',
            'weekly_report' => 'Weekly Business Report',
            'monthly_report' => 'Monthly Business Report',
            'product_performance' => 'Product Performance Report',
            'sales_performance' => 'Sales Performance Report',
            'profitability' => 'Profitability Report',
            'inventory' => 'Inventory Report',
            'business_opportunities' => 'AI Business Opportunities Report',
            default => 'Business Report',
        };
    }

    private static function scheduledEnabled(string $key): bool
    {
        return filter_var(Setting::where('key', $key)->value('value') ?? '1', FILTER_VALIDATE_BOOLEAN);
    }
}
