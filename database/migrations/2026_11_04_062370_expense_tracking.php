<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('expenses', function (Blueprint $table) {
            if (!Schema::hasColumn('expenses','supplier_payee')) $table->string('supplier_payee')->nullable()->after('notes');
            if (!Schema::hasColumn('expenses','payment_method')) $table->string('payment_method')->nullable()->after('supplier_payee');
            if (!Schema::hasColumn('expenses','description')) $table->text('description')->nullable()->after('category');
            if (!Schema::hasColumn('expenses','attachment_path')) $table->string('attachment_path')->nullable()->after('notes');
            if (!Schema::hasColumn('expenses','vat_status')) $table->enum('vat_status', ['recoverable','non_recoverable','not_applicable'])->default('not_applicable')->after('vat_amount');
        });
    }
    public function down(): void {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn(['supplier_payee','payment_method','description','attachment_path','vat_status']);
        });
    }
};
