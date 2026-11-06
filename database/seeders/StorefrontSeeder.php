<?php

namespace Database\Seeders;

use App\Models\Content;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

class StorefrontSeeder extends Seeder
{
    public function run(): void
    {
        $groups = config('storefront-content.groups', []);
        $fixture = database_path('seeders/data/storefront-content.json');

        $data = File::exists($fixture)
            ? json_decode(File::get($fixture), true)
            : [];

        $order = 0;

        foreach ($groups as $key => $group) {
            Content::firstOrCreate(
                ['key' => $key],
                [
                    'label' => $group['label'],
                    'type' => 'group',
                    'data' => $data[$key] ?? [],
                    'is_active' => true,
                    'sort_order' => $order,
                ]
            );

            $order++;
        }

        $settings = [
            'currency' => 'USD',
            'timezone' => 'UTC',
            'shipping_settings' => json_encode(['flat_rate' => 50, 'free_threshold' => 1000]),
            'tax_settings' => json_encode(['rate' => 20]),
            'payment_methods' => json_encode([]),
            'email_settings' => json_encode([]),
            'store_policies' => 'All products are for research purposes only.',
        ];

        foreach ($settings as $key => $value) {
            Setting::firstOrCreate(
                ['key' => $key],
                ['value' => $value, 'type' => 'string', 'is_secret' => false]
            );
        }

        Cache::forget(config('storefront-content.cache_key', 'storefront:content'));
    }
}
