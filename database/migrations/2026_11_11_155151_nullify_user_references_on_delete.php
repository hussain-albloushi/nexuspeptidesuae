<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deleting a customer is permanent (force delete). Every foreign key that
 * references users is switched to ON DELETE SET NULL so financial and audit
 * records (orders, carts, subscriptions, purchase approvals, activity log)
 * survive the removal. `customer_activities` keeps the row but loses the
 * link; the deletion entry records the customer's identity in its text.
 */
return new class extends Migration
{
    /** @var array<string, string> column => on-delete behaviour for up() */
    private const SET_NULL = [
        'orders.user_id',
        'carts.user_id',
        'subscriptions.user_id',
        'purchase_order_approvals.user_id',
        'supplier_purchase_orders.created_by',
        'supplier_purchase_orders.approved_by',
        'customer_activities.customer_id',
    ];

    public function up(): void
    {
        foreach (self::SET_NULL as $key) {
            [$table, $column] = explode('.', $key);
            Schema::table($table, function (Blueprint $blueprint) use ($column): void {
                $blueprint->dropForeign([$column]);
            });
            Schema::table($table, function (Blueprint $blueprint) use ($column): void {
                $blueprint->unsignedBigInteger($column)->nullable()->change();
            });
            Schema::table($table, function (Blueprint $blueprint) use ($column): void {
                $blueprint->foreign($column)->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        // Best-effort restore of the original constraints. Re-imposing
        // NOT NULL fails if orphaned NULLs exist, which is unavoidable
        // once customer rows have been permanently deleted.
        $notNull = ['orders.user_id', 'subscriptions.user_id', 'purchase_order_approvals.user_id', 'customer_activities.customer_id'];
        $cascade = ['customer_activities.customer_id'];

        foreach (self::SET_NULL as $key) {
            [$table, $column] = explode('.', $key);
            Schema::table($table, function (Blueprint $blueprint) use ($column): void {
                $blueprint->dropForeign([$column]);
            });
            Schema::table($table, function (Blueprint $blueprint) use ($column, $notNull, $key): void {
                $columnDefinition = $blueprint->unsignedBigInteger($column);
                if (in_array($key, $notNull, true)) {
                    $columnDefinition->change();
                } else {
                    $columnDefinition->nullable()->change();
                }
            });
            Schema::table($table, function (Blueprint $blueprint) use ($column, $cascade, $key): void {
                $foreign = $blueprint->foreign($column)->references('id')->on('users');
                if (in_array($key, $cascade, true)) {
                    $foreign->cascadeOnDelete();
                }
            });
        }
    }
};
