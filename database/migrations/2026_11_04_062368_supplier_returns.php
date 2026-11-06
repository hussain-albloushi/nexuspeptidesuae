<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('supplier_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->nullable()->constrained('supplier_purchase_orders');
            $table->foreignId('grn_id')->nullable()->constrained('goods_received_notes');
            $table->integer('returned_qty');
            $table->text('reason');
            $table->text('notes')->nullable();
            $table->decimal('inventory_reduced',10,4)->default(0);
            $table->string('documents_path')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('supplier_returns'); }
};
