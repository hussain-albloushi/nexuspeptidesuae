<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('supplier_purchase_orders')) return;
        Schema::table('supplier_purchase_orders', function (Blueprint $table) {
            $table->date('po_date')->nullable()->after('supplier_id');
            $table->date('expected_delivery')->nullable()->after('po_date');
            $table->string('currency')->default('AED')->after('expected_delivery');
            $table->decimal('exchange_rate',10,4)->default(1.0)->after('currency');
            $table->text('notes')->nullable()->after('status');
            $table->string('attachments_path')->nullable()->after('notes');
            $table->decimal('shipping_cost',10,2)->default(0)->after('attachments_path');
            $table->decimal('customs_cost',10,2)->default(0)->after('shipping_cost');
            $table->decimal('additional_charges',10,2)->default(0)->after('customs_cost');
        });
    }
    public function down(): void {
        Schema::table('supplier_purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['po_date','expected_delivery','currency','exchange_rate','notes','attachments_path','shipping_cost','customs_cost','additional_charges']);
        });
    }
};
