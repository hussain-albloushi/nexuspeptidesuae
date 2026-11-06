<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::create('subscriptions', function (Blueprint $table) {
        $table->id(); $table->foreignId('user_id')->constrained('users');
        $table->foreignId('subscription_plan_id')->constrained('subscription_plans');
        $table->string('stripe_subscription_id')->nullable(); $table->string('status')->default('active');
        $table->dateTime('trial_ends_at')->nullable(); $table->dateTime('current_period_ends_at');
        $table->dateTime('canceled_at')->nullable(); $table->decimal('amount',10,2); $table->timestamps();
    });}
    public function down(): void { Schema::dropIfExists('subscriptions'); }
};