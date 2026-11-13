<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// AI Business Assistant — keep recommendations fresh and generate enabled scheduled reports.
// Expensive LLM work never runs here; everything below is deterministic analytics.
Schedule::command('ai:refresh --recommendations')->dailyAt('07:30');
Schedule::command('ai:refresh --reports --report=daily_report')->dailyAt('08:00');
Schedule::command('ai:refresh --reports --report=weekly_report')->weeklyOn(1, '08:00');
Schedule::command('ai:refresh --reports --report=monthly_report')->monthlyOn(1, '08:00');
