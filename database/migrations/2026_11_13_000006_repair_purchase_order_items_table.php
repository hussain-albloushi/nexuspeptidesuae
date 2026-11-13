<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Repairs installations whose purchase_order_items table was created by an
 * earlier schema version and then marked as migrated, leaving header columns
 * (supplier_id, po_number...) instead of line items. The table cannot hold
 * usable rows in that shape, so it is rebuilt in place.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('purchase_order_items', 'purchase_order_id')) {
            return;
        }

        Schema::drop('purchase_order_items');

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('supplier_purchase_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('product_name_snapshot');
            $table->integer('quantity');
            $table->decimal('unit_cost', 10, 2);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('tax', 10, 2)->default(0);
            $table->decimal('line_total', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Irreversible: the pre-repair shape could not store line items.
    }
};
