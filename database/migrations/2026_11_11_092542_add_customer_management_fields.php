<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('phone', 50)->nullable();
            $table->string('customer_status', 20)->default('active')->index();
            $table->string('segment', 100)->nullable();
            $table->string('location')->nullable();
            $table->text('customer_notes')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->softDeletes();
        });
        Schema::create('customer_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->string('description');
            $table->string('actor_name')->nullable();
            $table->timestamp('created_at');
            $table->index(['customer_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_activities');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['phone', 'customer_status', 'segment', 'location', 'customer_notes', 'last_activity_at', 'deleted_at']);
        });
    }
};
