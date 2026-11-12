<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReturnRefundTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_request_a_return_inside_the_window(): void
    {
        Sanctum::actingAs($customer = $this->customer());
        $order = $this->deliveredOrder(['user_id' => $customer->id]);

        $this->postJson('/api/orders/'.$order->id.'/return', ['reason' => 'The vial arrived damaged in transit.'])
            ->assertCreated()
            ->assertJsonPath('return_request.status', 'requested');

        $this->assertDatabaseHas('return_requests', ['order_id' => $order->id, 'status' => 'requested']);
        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $order->id, 'new_status' => 'delivered', 'notes' => 'Return requested by the customer. Our team will review it shortly.',
        ]);
    }

    public function test_return_request_outside_the_window_is_rejected(): void
    {
        $this->storeSettings(['returnWindowDays' => 7]);
        Sanctum::actingAs($customer = $this->customer());
        $order = $this->deliveredOrder(['user_id' => $customer->id, 'delivered_days_ago' => 8]);

        $this->postJson('/api/orders/'.$order->id.'/return', ['reason' => 'The vial arrived damaged in transit.'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('order');
        $this->assertDatabaseCount('return_requests', 0);
    }

    public function test_return_window_can_be_disabled_and_only_delivered_paid_orders_qualify(): void
    {
        $this->storeSettings(['returnWindowDays' => 0]);
        Sanctum::actingAs($customer = $this->customer());

        $this->postJson('/api/orders/'.$this->deliveredOrder(['user_id' => $customer->id])->id.'/return', ['reason' => 'The vial arrived damaged in transit.'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('order');

        $pending = $this->deliveredOrder(['user_id' => $customer->id, 'status' => 'processing']);
        $this->postJson('/api/orders/'.$pending->id.'/return', ['reason' => 'I changed my mind about this order.'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('order');
    }

    public function test_a_second_open_request_is_rejected_until_the_first_is_rejected(): void
    {
        Sanctum::actingAs($customer = $this->customer());
        $order = $this->deliveredOrder(['user_id' => $customer->id]);
        $this->postJson('/api/orders/'.$order->id.'/return', ['reason' => 'The vial arrived damaged in transit.'])->assertCreated();

        $this->postJson('/api/orders/'.$order->id.'/return', ['reason' => 'The vial arrived damaged in transit.'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('order');
    }

    public function test_customers_cannot_request_returns_on_other_customers_orders(): void
    {
        Sanctum::actingAs($this->customer());

        $this->postJson('/api/orders/'.$this->deliveredOrder()->id.'/return', ['reason' => 'The vial arrived damaged in transit.'])
            ->assertNotFound();
    }

    public function test_admin_approval_refunds_cancels_and_restocks_the_order(): void
    {
        Sanctum::actingAs($customer = $this->customer());
        $product = $this->product(['stock_quantity' => 10, 'low_stock_threshold' => 2]);
        $order = $this->deliveredOrder(['user_id' => $customer->id, 'total_amount' => 200]);
        $this->saleMovement($order, $product, 10, 8);

        $this->postJson('/api/orders/'.$order->id.'/return', ['reason' => 'The vial arrived damaged in transit.'])->assertCreated();
        $returnId = ReturnRequest::where('order_id', $order->id)->first()->id;

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->patchJson('/api/orders/'.$order->id.'/returns/'.$returnId, [
            'decision' => 'approved', 'refund_amount' => 200, 'decision_notes' => 'Verified with the courier.',
        ])->assertOk()->assertJsonPath('order.status', 'cancelled')
            ->assertJsonPath('order.payment_status', 'refunded');

        $this->assertDatabaseHas('return_requests', ['id' => $returnId, 'status' => 'approved', 'refund_amount' => 200]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled', 'shipping_status' => 'cancelled', 'payment_status' => 'refunded']);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 12]);
        $this->assertDatabaseHas('inventory_transactions', ['product_id' => $product->id, 'type' => 'return', 'reason' => 'Returned order '.$order->order_number, 'quantity_before' => 10, 'quantity_after' => 12]);
        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $order->id, 'old_status' => 'delivered', 'new_status' => 'cancelled',
        ]);
    }

    public function test_partial_refund_approval_marks_the_order_partially_refunded(): void
    {
        Sanctum::actingAs($customer = $this->customer());
        $order = $this->deliveredOrder(['user_id' => $customer->id, 'total_amount' => 200]);

        $this->postJson('/api/orders/'.$order->id.'/return', ['reason' => 'One pen arrived damaged in transit.'])->assertCreated();
        $returnId = ReturnRequest::where('order_id', $order->id)->first()->id;

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->patchJson('/api/orders/'.$order->id.'/returns/'.$returnId, ['decision' => 'approved', 'refund_amount' => 50])
            ->assertOk()
            ->assertJsonPath('order.payment_status', 'partially_refunded');
    }

    public function test_refund_approval_cannot_exceed_the_order_total(): void
    {
        Sanctum::actingAs($customer = $this->customer());
        $order = $this->deliveredOrder(['user_id' => $customer->id, 'total_amount' => 200]);

        $this->postJson('/api/orders/'.$order->id.'/return', ['reason' => 'The vial arrived damaged in transit.'])->assertCreated();
        $returnId = ReturnRequest::where('order_id', $order->id)->first()->id;

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->patchJson('/api/orders/'.$order->id.'/returns/'.$returnId, ['decision' => 'approved', 'refund_amount' => 250])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('refund_amount');
        $this->assertDatabaseHas('return_requests', ['id' => $returnId, 'status' => 'requested']);
    }

    public function test_a_return_request_can_only_be_decided_once(): void
    {
        Sanctum::actingAs($customer = $this->customer());
        $order = $this->deliveredOrder(['user_id' => $customer->id]);

        $this->postJson('/api/orders/'.$order->id.'/return', ['reason' => 'The vial arrived damaged in transit.'])->assertCreated();
        $returnId = ReturnRequest::where('order_id', $order->id)->first()->id;

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->patchJson('/api/orders/'.$order->id.'/returns/'.$returnId, ['decision' => 'rejected', 'decision_notes' => 'No verification from the courier.'])->assertOk();
        $this->assertDatabaseHas('return_requests', ['id' => $returnId, 'status' => 'rejected']);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'delivered']);

        $this->patchJson('/api/orders/'.$order->id.'/returns/'.$returnId, ['decision' => 'approved', 'refund_amount' => 100])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('decision');
    }

    public function test_admins_can_record_a_refund_performed_with_the_payment_provider(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $order = $this->deliveredOrder(['total_amount' => 200, 'payment_status' => 'paid']);

        $this->patchJson('/api/orders/'.$order->id.'/refund', ['amount' => 80, 'notes' => 'Refunded via the Stripe dashboard.'])
            ->assertOk()
            ->assertJsonPath('order.payment_status', 'partially_refunded');

        $this->patchJson('/api/orders/'.$order->id.'/refund', ['amount' => 120, 'notes' => 'Remaining balance refunded via the Stripe dashboard.'])
            ->assertOk()
            ->assertJsonPath('order.payment_status', 'refunded');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'refunded']);
    }

    public function test_recorded_refunds_cannot_exceed_the_order_total(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $order = $this->deliveredOrder(['total_amount' => 200, 'payment_status' => 'paid']);

        $this->patchJson('/api/orders/'.$order->id.'/refund', ['amount' => 150, 'notes' => 'Partial refund via the Stripe dashboard.'])->assertOk();

        $this->patchJson('/api/orders/'.$order->id.'/refund', ['amount' => 100, 'notes' => 'Second refund would exceed the order total.'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount');
    }

    public function test_only_paid_orders_can_record_refunds(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $order = $this->deliveredOrder(['payment_status' => 'pending']);

        $this->patchJson('/api/orders/'.$order->id.'/refund', ['amount' => 100, 'notes' => 'Refund attempted before payment.'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payment');
    }

    private function customer(): User
    {
        return User::factory()->create();
    }

    private function product(array $attributes = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Research Product', 'slug' => 'research-product', 'sku' => 'TEST-PRODUCT',
            'price' => 100, 'stock_quantity' => 10, 'is_active' => true,
        ], $attributes));
    }

    /**
     * @param  array{status?: string, total_amount?: float, payment_status?: string, delivered_days_ago?: int}  $attributes
     */
    private function deliveredOrder(array $attributes = []): Order
    {
        $daysAgo = $attributes['delivered_days_ago'] ?? 1;
        unset($attributes['delivered_days_ago']);
        $order = Order::create(array_merge([
            'user_id' => User::factory()->create()->id,
            'order_number' => 'NP-'.fake()->unique()->numerify('########'),
            'status' => 'delivered', 'payment_status' => 'paid',
            'subtotal' => 200, 'total_amount' => 200,
            'payment_method' => 'cod',
        ], $attributes));

        OrderStatusHistory::create([
            'order_id' => $order->id, 'old_status' => 'shipped', 'new_status' => 'delivered',
        ])->forceFill(['created_at' => now()->subDays($daysAgo)])->save();

        return $order;
    }

    private function saleMovement(Order $order, Product $product, int $before, int $after): void
    {
        InventoryTransaction::create([
            'product_id' => $product->id, 'quantity_before' => $before, 'quantity_after' => $after,
            'type' => 'sale', 'reason' => 'Order '.$order->order_number,
        ]);
    }

    private function storeSettings(array $settings): void
    {
        Content::updateOrCreate(
            ['key' => 'store'],
            [
                'label' => 'Store', 'type' => 'group', 'is_active' => true,
                'data' => array_merge([
                    'shippingMode' => 'flat', 'flatRate' => 25, 'freeShippingThreshold' => null,
                    'returnWindowDays' => 14,
                ], $settings),
            ]
        );
    }
}
