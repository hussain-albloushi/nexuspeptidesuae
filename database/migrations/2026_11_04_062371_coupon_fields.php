<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('coupons', function (Blueprint $table) {
            if (!Schema::hasColumn('coupons','per_customer_limit')) $table->integer('per_customer_limit')->nullable()->after('usage_limit');
            if (!Schema::hasColumn('coupons','product_restrictions')) $table->json('product_restrictions')->nullable()->after('per_customer_limit');
            if (!Schema::hasColumn('coupons','category_restrictions')) $table->json('category_restrictions')->nullable()->after('product_restrictions');
            if (!Schema::hasColumn('coupons','usage_count')) $table->integer('usage_count')->default(0); // already exists possibly
        });
    }
    public function down(): void {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropColumn(['per_customer_limit','product_restrictions','category_restrictions']);
        });
    }
};
