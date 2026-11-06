<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('goods_received_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('supplier_purchase_orders')->cascadeOnDelete();
            $table->string('po_number');
            $table->string('reference'); // supplier code
            $table->date('delivery_date');
            $table->decimal('accepted_qty',10,4)->default(0);
            $table->decimal('damaged_qty',10,4)->default(0);
            $table->decimal('rejected_qty',10,4)->default(0);
            $table->integer('total_line_item_qty');
            $table->text('notes')->nullable();
            $table->text('batch_numbers')->nullable();
            $table->json('expiry_dates')->nullable();
            $table->string('documents_path')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void {}
};
