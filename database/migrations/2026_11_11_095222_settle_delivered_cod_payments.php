<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('orders')->where('status', 'delivered')->where('payment_method', 'cod')
            ->whereIn('payment_status', ['pending', 'unpaid'])->orderBy('id')
            ->chunkById(200, function ($orders): void {
                DB::transaction(function () use ($orders): void {
                    $current = DB::table('orders')->whereIn('id', $orders->pluck('id'))
                        ->where('status', 'delivered')->where('payment_method', 'cod')
                        ->whereIn('payment_status', ['pending', 'unpaid'])->lockForUpdate()->get();
                    foreach ($current as $order) {
                        DB::table('orders')->where('id', $order->id)->update(['payment_status' => 'paid', 'updated_at' => now()]);
                        DB::table('order_status_history')->insert([
                            'order_id' => $order->id,
                            'old_status' => 'delivered',
                            'new_status' => 'delivered',
                            'notes' => 'Cash-on-delivery payment marked paid under the delivery settlement rule.',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }, 3);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Settlement records are retained to avoid reversing subsequent financial activity.
    }
};
