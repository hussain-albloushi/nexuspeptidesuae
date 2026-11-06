<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category'); // COGS / Shipping / Tax / Maintenance / Marketing
            $table->date('date');
            $table->decimal('amount',10,2);
            $table->string('currency')->default('AED');
            $table->boolean('vat_inclusive')->default(false);
            $table->decimal('vat_amount',10,2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('expenses'); }
};
