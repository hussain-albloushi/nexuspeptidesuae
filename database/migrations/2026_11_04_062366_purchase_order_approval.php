<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('supplier_purchase_orders', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approval_date')->nullable();
            $table->text('rejection_reason')->nullable();
        });
        Schema::create('purchase_order_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('supplier_purchase_orders')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->string('action'); // approve / reject / override
            $table->text('reason')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::table('supplier_purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['created_by']); $table->dropForeign(['approved_by']);
            $table->dropColumn(['created_by','approved_by','approval_date','rejection_reason']);
        });
        Schema::dropIfExists('purchase_order_approvals');
    }
};
