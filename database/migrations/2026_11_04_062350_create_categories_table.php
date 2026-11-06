<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        $tables = ['product_variants','cart_items','orders','order_items','coupons','subscriptions','subscription_plans','carts','products','categories'];
        foreach ($tables as $table) Schema::dropIfExists($table);
        Schema::create('categories', function (Blueprint $table) {
            $table->id(); $table->foreignId('parent_id')->nullable()->constrained('categories');
            $table->string('name')->unique(); $table->string('slug')->unique(); $table->text('description')->nullable();
            $table->boolean('is_active')->default(true); $table->string('image_url')->nullable(); $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('categories'); }
};