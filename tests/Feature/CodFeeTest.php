<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CodFeeTest extends TestCase
{
    use RefreshDatabase;

    public function test_cod_order_adds_configured_fee_to_total_and_records_it(): void
    {
        $this->storeSettings(['codFee' => 15]);
        $product = $this->product();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/orders', $this->payload($product))
            ->assertCreated()
            ->assertJsonPath('subtotal', '200.00')
            ->assertJsonPath('shipping_amount', '25.00')
            ->assertJsonPath('tax_amount', '10.00')
            ->assertJsonPath('cod_fee', '15.00')
            ->assertJsonPath('total_amount', '250.00');

        $this->assertDatabaseHas('orders', ['cod_fee' => 15.00, 'total_amount' => 250.00]);
    }

    public function test_cod_order_without_configured_fee_charges_nothing(): void
    {
        $this->storeSettings(['shippingMode' => 'flat', 'flatRate' => 25, 'freeShippingThreshold' => null]);
        $product = $this->product();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/orders', $this->payload($product))
            ->assertCreated()
            ->assertJsonPath('cod_fee', '0.00')
            ->assertJsonPath('total_amount', '235.00');
    }

    public function test_storefront_settings_expose_the_cod_fee(): void
    {
        $this->storeSettings(['codFee' => 12.5]);

        $this->getJson('/api/store-settings')
            ->assertOk()
            ->assertJsonPath('codFee', 12.5);
    }

    private function product(array $attributes = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Research Product', 'slug' => 'research-product', 'sku' => 'TEST-PRODUCT',
            'price' => 100, 'stock_quantity' => 10, 'is_active' => true,
        ], $attributes));
    }

    private function payload(Product $product): array
    {
        return [
            'items' => [['slug' => $product->slug, 'qty' => 2]],
            'shipping_address' => [
                'name' => 'Test Customer', 'email' => 'customer@example.com', 'phone' => '0501234567',
                'address' => '123 Test Street', 'city' => 'Dubai', 'country' => 'United Arab Emirates',
            ],
            'payment_method' => 'cod',
        ];
    }

    private function storeSettings(array $settings): void
    {
        Content::updateOrCreate(
            ['key' => 'store'],
            [
                'label' => 'Store', 'type' => 'group', 'is_active' => true,
                'data' => array_merge([
                    'vatEnabled' => 'true', 'vatRate' => 5, 'vatIncludedInPrice' => 'false',
                    'shippingMode' => 'flat', 'flatRate' => 25, 'freeShippingThreshold' => null,
                ], $settings),
            ]
        );
    }
}
