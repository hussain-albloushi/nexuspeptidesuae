<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Table used by the AI Recommendations engine. */
    public function up(): void
    {
        Schema::create('ai_recommendations', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->string('category'); // e.g. "Increase Revenue", "Improve Profitability"
            $table->enum('priority', ['critical', 'high', 'medium', 'low']);
            $table->enum('status', ['new', 'reviewed', 'in_progress', 'completed', 'dismissed'])->default('new');
            $table->longText('reasoning');
            $table->longText('suggested_action');
            $table->longText('expected_impact');
            $table->longText('supporting_data');
            $table->json('relevant_products')->nullable();
            $table->json('relevant_categories')->nullable();
            $table->string('stability_signature')->nullable()
                ->comment('Hash of the underlying data pattern — used to avoid regenerating the same recommendation.');
            $table->text('internal_notes')->nullable();
            $table->timestamp('implemented_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'priority']);
            $table->index(['category', 'status']);
            $table->index('stability_signature');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_recommendations');
    }
};
