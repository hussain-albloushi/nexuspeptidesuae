<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('supplier_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('supplier_product_code')->nullable();
            $table->decimal('purchase_price',10,2);
            $table->string('currency')->default('AED');
            $table->integer('min_order_qty')->default(1);
            $table->integer('lead_time_days')->default(7);
            $table->boolean('preferred')->default(false);
            $table->decimal('last_purchase_price',10,2)->nullable();
            $table->json('historical_prices')->nullable();
            $table->timestamps();
        });
        Schema::create('supplier_purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->string('po_number')->unique();
            $table->decimal('total_amount',10,2);
            $table->string('status')->default('draft');
            $table->timestamps();
        });
        Schema::table('suppliers', function (Blueprint $table) {
            $table->enum('status', ['active','inactive','on_hold','pending_review'])->default('active')->change();
        });
    }
    public function down(): void {
        Schema::dropIfExists('supplier_purchase_orders');
        Schema::dropIfExists('supplier_products');
    }
};
