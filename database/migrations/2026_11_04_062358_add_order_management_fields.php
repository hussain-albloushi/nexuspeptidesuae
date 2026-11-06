<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders','tracking_number')) $table->string('tracking_number')->nullable()->after('shipping_address');
            if (!Schema::hasColumn('orders','payment_status')) $table->string('payment_status')->default('pending')->after('payment_method');
            if (!Schema::hasColumn('orders','shipping_status')) $table->string('shipping_status')->default('pending')->after('payment_status');
            if (!Schema::hasColumn('orders','internal_notes')) $table->text('internal_notes')->nullable()->after('notes');
        });
    }
    public function down(): void {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['tracking_number','payment_status','shipping_status','internal_notes']);
        });
    }
};
