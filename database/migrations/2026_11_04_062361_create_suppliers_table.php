<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('supplier_code')->unique();
            $table->string('legal_name');
            $table->string('trading_name')->nullable();
            $table->string('contact_person');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('country')->default('UAE');
            $table->string('city');
            $table->text('address');
            $table->string('tax_reg_number')->nullable();
            $table->string('payment_terms')->nullable();
            $table->string('default_currency')->default('AED');
            $table->enum('status', ['active','inactive','pending_review'])->default('active');
            $table->text('notes')->nullable();
            $table->string('documents_path')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('suppliers'); }
};
