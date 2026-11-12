<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StripeCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_card_order_in_dummy_mode_is_recorded_as_paid(): void
    {
        config(['services.stripe.dummy' => true]);
        $this->storeSettings(['shippingMode' => 'flat', 'flatRate' => 25, 'freeShippingThreshold' => null]);
        $product = $this->product();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/orders', $this->payload($product))
            ->assertCreated()
            ->assertJsonPath('payment_method', 'stripe')
            ->assertJsonPath('payment_status', 'paid')
            ->assertJsonPath('cod_fee', '0.00')
            ->assertJsonPath('total_amount', '235.00')
            ->assertJsonPath('checkout_url', null)
            ->assertJsonPath('dummy_payment', true);

        $this->assertDatabaseHas('orders', [
            'payment_method' => 'stripe',
            'payment_status' => 'paid',
            'cod_fee' => 0.00,
        ]);

        $this->assertDatabaseCount('inventory_transactions', 1);
    }

    public function test_card_order_dummy_mode_writes_a_paid_note_to_the_order_history(): void
    {
        config(['services.stripe.dummy' => true]);
        $product = $this->product();
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/orders', $this->payload($product))->assertCreated();

        $order = Order::query()->where('order_number', $response->json('order_number'))->firstOrFail();

        $this->assertSame('paid', $order->payment_status);
        $this->assertTrue(
            $order->statusHistory()->get()->pluck('notes')->filter()->contains(fn (?string $notes) => str_contains((string) $notes, 'dummy')),
            'Expected a status history note describing the dummy payment.'
        );
    }

    public function test_webhook_acknowledges_events_in_dummy_mode_without_changing_state(): void
    {
        config(['services.stripe.dummy' => true]);

        $this->postJson('/api/stripe/webhook', ['type' => 'checkout.session.completed'])
            ->assertOk()
            ->assertJsonPath('received', true)
            ->assertJsonPath('dummy', true);
    }

    public function test_webhook_rejects_bad_signatures_in_live_mode(): void
    {
        config(['services.stripe.dummy' => false, 'services.stripe.webhook_secret' => 'whsec_dummy_not_a_real_secret']);

        $this->call('POST', '/api/stripe/webhook', [], [], [], ['HTTP_STRIPE_SIGNATURE' => 't=1,v1=bad'], '{"type":"checkout.session.completed"}')
            ->assertStatus(400);
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
            'payment_method' => 'stripe',
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
