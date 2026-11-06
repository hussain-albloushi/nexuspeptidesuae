<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('cost_price', 10, 2)->nullable()->after('compare_at_price');
            $table->integer('stock_quantity')->default(0)->after('is_active');
            $table->string('stock_status')->default('in_stock')->after('stock_quantity');
            $table->boolean('featured')->default(false)->after('requires_subscription');
            $table->string('visibility')->default('public')->after('featured');
            $table->string('seo_title')->nullable()->after('visibility');
            $table->text('seo_description')->nullable()->after('seo_title');
            $table->softDeletes()->after('updated_at');
        });
    }
    public function down(): void {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['cost_price','stock_quantity','stock_status','featured','visibility','seo_title','seo_description','deleted_at']);
        });
    }
};
