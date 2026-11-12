<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_uses_database_prices_and_reduces_stock(): void
    {
        $product = $this->product();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/orders', $this->payload($product) + ['total_amount' => 1])
            ->assertCreated()->assertJsonPath('subtotal', '200.00')
            ->assertJsonPath('tax_amount', '10.00')->assertJsonPath('shipping_amount', '25.00')
            ->assertJsonPath('total_amount', '235.00');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 8]);
        $this->assertDatabaseHas('inventory_transactions', ['product_id' => $product->id, 'type' => 'sale']);
    }

    public function test_vat_included_in_prices_is_not_added_again(): void
    {
        $product = $this->product(['price' => 105]);
        $this->storeSettings(['vatIncludedInPrice' => 'true']);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/orders', $this->payload($product))
            ->assertCreated()->assertJsonPath('tax_amount', '10.00')
            ->assertJsonPath('shipping_amount', '25.00')
            ->assertJsonPath('total_amount', '235.00');
    }

    public function test_flat_shipping_uses_the_configured_delivery_charge(): void
    {
        $product = $this->product();
        $this->storeSettings(['shippingMode' => 'flat', 'flatRate' => 25]);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/orders', $this->payload($product))
            ->assertCreated()->assertJsonPath('shipping_amount', '25.00')
            ->assertJsonPath('total_amount', '235.00');
    }

    public function test_inactive_product_returns_422_without_creating_an_order(): void
    {
        $product = $this->product(['is_active' => false]);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/orders', $this->payload($product))->assertUnprocessable();

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    public function test_insufficient_stock_rolls_back_the_entire_order(): void
    {
        $product = $this->product();
        $unavailable = $this->product(['slug' => 'sold-out', 'sku' => 'SOLD-OUT', 'stock_quantity' => 0]);
        $coupon = Coupon::create(['code' => 'RETRY', 'type' => 'fixed', 'value' => 10, 'is_active' => true]);
        Sanctum::actingAs(User::factory()->create());
        $payload = $this->payload($product);
        $payload['items'][] = ['slug' => $unavailable->slug, 'qty' => 1];
        $payload['coupon_code'] = 'RETRY';

        $this->postJson('/api/orders', $payload)->assertUnprocessable();

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 10]);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('inventory_transactions', 0);
        $this->assertDatabaseHas('coupons', ['id' => $coupon->id, 'usage_count' => 0]);
    }

    public function test_successful_checkout_consumes_one_coupon_use(): void
    {
        $product = $this->product();
        $coupon = Coupon::create([
            'code' => 'ONCE', 'type' => 'fixed', 'value' => 10, 'is_active' => true, 'usage_limit' => 1,
        ]);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/orders', $this->payload($product) + ['coupon_code' => 'once'])
            ->assertCreated()->assertJsonPath('discount_amount', '10.00');

        $this->assertDatabaseHas('coupons', ['id' => $coupon->id, 'usage_count' => 1]);

        $this->postJson('/api/orders', $this->payload($product) + ['coupon_code' => 'ONCE'])
            ->assertUnprocessable()->assertJsonValidationErrors('coupon_code');

        $this->assertDatabaseCount('orders', 1);
    }

    public function test_missing_shipping_details_returns_422(): void
    {
        $product = $this->product();
        Sanctum::actingAs(User::factory()->create());
        $payload = $this->payload($product);
        $payload['shipping_address'] = ['country' => 'United Arab Emirates'];

        $this->postJson('/api/orders', $payload)->assertUnprocessable()
            ->assertJsonValidationErrors(['shipping_address.name', 'shipping_address.email', 'shipping_address.address']);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_guest_checkout_returns_401(): void
    {
        $this->postJson('/api/orders', [])->assertUnauthorized();

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_card_checkout_is_accepted(): void
    {
        // Card orders are accepted; in dummy mode they are recorded as paid.
        $product = $this->product();
        Sanctum::actingAs(User::factory()->create());
        $payload = $this->payload($product);
        $payload['payment_method'] = 'stripe';

        config(['services.stripe.dummy' => true]);

        $this->postJson('/api/orders', $payload)->assertCreated();

        $this->assertDatabaseHas('orders', ['payment_method' => 'stripe', 'payment_status' => 'paid']);
        $this->assertDatabaseCount('inventory_transactions', 1);
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
                    'shippingMode' => 'flat', 'flatRate' => 25, 'freeShippingThreshold' => 300,
                    'codFee' => 0, 'vatEnabled' => true, 'vatRate' => 5, 'vatIncludedInPrice' => false,
                ], $settings),
            ]
        );
    }
}
