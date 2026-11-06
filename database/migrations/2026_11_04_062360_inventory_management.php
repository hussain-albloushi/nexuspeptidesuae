<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products','low_stock_threshold')) $table->integer('low_stock_threshold')->default(5)->after('stock_status');
        });
        Schema::create('inventory_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->integer('quantity_before');
            $table->integer('quantity_after');
            $table->string('type'); // adjustment, sale, return, reserve, release
            $table->text('reason')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::table('products', function (Blueprint $table) { $table->dropColumn('low_stock_threshold'); });
        Schema::dropIfExists('inventory_transactions');
    }
};
