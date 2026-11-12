<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            $record = DB::table('contents')->where('key', 'store')->lockForUpdate()->first();
            $data = $record ? (json_decode($record->data ?? '{}', true, 512, JSON_THROW_ON_ERROR) ?: []) : [];
            $data = array_replace($data, [
                'shippingMode' => 'flat',
                'flatRate' => 25,
                'freeShippingThreshold' => 300,
            ]);
            if ($record) {
                DB::table('contents')->where('id', $record->id)->update([
                    'data' => json_encode($data, JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('contents')->insert([
                    'key' => 'store', 'label' => 'Store', 'type' => 'group',
                    'data' => json_encode($data, JSON_THROW_ON_ERROR),
                    'is_active' => true, 'sort_order' => 2,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });
        Cache::forget(config('storefront-content.cache_key', 'storefront:content'));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Retain merchant shipping settings rather than resetting later edits.
    }
};
