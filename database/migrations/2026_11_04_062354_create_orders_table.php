<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::create('orders', function (Blueprint $table) {
        $table->id(); $table->foreignId('user_id')->constrained('users');
        $table->foreignId('cart_id')->nullable()->constrained('carts');
        $table->string('order_number')->unique(); $table->string('status')->default('pending');
        $table->decimal('subtotal',10,2); $table->decimal('tax_amount',10,2)->default(0);
        $table->decimal('discount_amount',10,2)->default(0); $table->decimal('shipping_amount',10,2)->default(0);
        $table->decimal('total_amount',10,2); $table->string('payment_method')->nullable();
        $table->text('shipping_address')->nullable(); $table->text('billing_address')->nullable(); $table->text('notes')->nullable();
        $table->timestamps();
    });}
    public function down(): void { Schema::dropIfExists('orders'); }
};