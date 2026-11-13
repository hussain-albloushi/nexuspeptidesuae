<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Generated AI business reports. */
    public function up(): void
    {
        Schema::create('ai_reports', function (Blueprint $table) {
            $table->id();
            $table->string('type')
                ->comment('daily_report|weekly_report|monthly_report|product_performance|sales_performance|profitability|inventory|business_opportunities');
            $table->string('period_start');
            $table->string('period_end');
            $table->text('content'); // Markdown report body
            $table->boolean('scheduled')->default(false);
            $table->timestamps();

            $table->index(['type', 'period_start', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_reports');
    }
};
