<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        if (Schema::hasTable('supplier_purchase_orders')) return;
        Schema::create('supplier_purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->string('po_number')->unique();
            $table->date('po_date');
            $table->date('expected_delivery')->nullable();
            $table->string('currency')->default('AED');
            $table->decimal('exchange_rate',10,4)->default(1.0);
            $table->decimal('total_amount',10,2);
            $table->string('status')->default('draft');
            $table->text('notes')->nullable();
            $table->string('attachments_path')->nullable();
            $table->decimal('shipping_cost',10,2)->default(0);
            $table->decimal('customs_cost',10,2)->default(0);
            $table->decimal('additional_charges',10,2)->default(0);
            $table->timestamps();
        });
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('supplier_purchase_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('product_name_snapshot');
            $table->integer('quantity');
            $table->decimal('unit_cost',10,2);
            $table->decimal('discount',10,2)->default(0);
            $table->decimal('tax',10,2)->default(0);
            $table->decimal('line_total',10,2);
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('supplier_purchase_orders');
    }
};
