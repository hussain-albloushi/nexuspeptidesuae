<?php

namespace App\Services\AI;

use Carbon\Carbon;

/**
 * Simple date-range resolver used across the AI module.
 *
 * The current window is read from the request; the comparison window is
 * always the same-length period immediately preceding it. Callers that want
 * "this month vs last month" may pass compareStart/compareEnd explicitly.
 */
class DateRange
{
    public readonly Carbon $start;

    public readonly Carbon $end;

    private function __construct(Carbon $start, Carbon $end)
    {
        $this->start = $start->copy()->startOfDay();
        $this->end = $end->copy()->endOfDay();
    }

    public static function from(string $start, string $end): self
    {
        return new self(Carbon::parse($start), Carbon::parse($end));
    }

    /** Last N full days including today. */
    public static function lastDays(int $days): self
    {
        $end = now()->endOfDay();
        $start = now()->copy()->subDays($days - 1)->startOfDay();

        return new self($start, $end);
    }

    /** Current calendar month to date. */
    public static function thisMonth(): self
    {
        return new self(now()->copy()->startOfMonth(), now()->copy()->endOfDay());
    }

    /** Previous calendar month (full). */
    public static function previousMonth(): self
    {
        $start = now()->copy()->subMonthNoOverflow()->startOfMonth();

        return new self($start, $start->copy()->endOfMonth());
    }

    /** Current calendar year to date. */
    public static function thisYear(): self
    {
        return new self(now()->copy()->startOfYear(), now()->copy()->endOfDay());
    }

    /** Today. */
    public static function today(): self
    {
        return new self(now()->copy()->startOfDay(), now()->copy()->endOfDay());
    }

    /** Yesterday. */
    public static function yesterday(): self
    {
        $day = now()->copy()->subDay();

        return new self($day->copy()->startOfDay(), $day->copy()->endOfDay());
    }

    /** Explicit previous-window from this window; otherwise infer same-length preceding period. */
    public function previous(): self
    {
        $length = abs($this->end->diffInSeconds($this->start));
        $end = $this->start->copy()->subSecond();

        return new self($end->copy()->subSeconds($length), $end);
    }

    /** @return array{start: string, end: string} */
    public function toArray(): array
    {
        return ['start' => $this->start->toDateString(), 'end' => $this->end->toDateString()];
    }

    public function diffInDays(): float
    {
        return max(1, $this->start->diffInDays($this->end));
    }
}
