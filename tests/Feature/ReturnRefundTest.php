<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\OrderItem;
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

    public function test_customer_can_request_a_return_for_specific_items_inside_the_window(): void
    {
        Sanctum::actingAs($customer = $this->customer());
        $order = $this->deliveredOrder(['user_id' => $customer->id]);
        [$item] = $this->orderItems($order, [['name' => 'BPC-157 10mg', 'price' => 100, 'quantity' => 1]]);

        $this->postJson('/api/orders/'.$order->id.'/return', [
            'reason' => 'The vial arrived damaged in transit.',
            'items' => [['order_item_id' => $item->id, 'quantity' => 1]],
        ])->assertCreated()
            ->assertJsonPath('return_request.status', 'requested');

        $this->assertDatabaseHas('return_requests', ['order_id' => $order->id, 'status' => 'requested']);
        $this->assertDatabaseHas('return_request_items', ['return_request_id' => ReturnRequest::first()->id, 'order_item_id' => $item->id, 'quantity' => 1]);
        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $order->id, 'new_status' => 'delivered', 'notes' => 'Return requested for 1 × BPC-157 10mg. Our team will review it shortly.',
        ]);
    }

    public function test_return_request_outside_the_window_is_rejected(): void
    {
        $this->storeSettings(['returnWindowDays' => 7]);
        Sanctum::actingAs($customer = $this->customer());
        $order = $this->deliveredOrder(['user_id' => $customer->id, 'delivered_days_ago' => 8]);
        [$item] = $this->orderItems($order, [['name' => 'BPC-157 10mg', 'price' => 100, 'quantity' => 1]]);

        $this->postJson('/api/orders/'.$order->id.'/return', $this->returnPayload('The vial arrived damaged in transit.', $item))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('order');
        $this->assertDatabaseCount('return_requests', 0);
    }

    public function test_return_window_can_be_disabled_and_only_delivered_paid_orders_qualify(): void
    {
        $this->storeSettings(['returnWindowDays' => 0]);
        Sanctum::actingAs($customer = $this->customer());

        $order = $this->deliveredOrder(['user_id' => $customer->id]);
        [$item] = $this->orderItems($order, [['name' => 'BPC-157 10mg', 'price' => 100, 'quantity' => 1]]);

        $this->postJson('/api/orders/'.$order->id.'/return', $this->returnPayload('The vial arrived damaged in transit.', $item))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('order');

        $pending = $this->deliveredOrder(['user_id' => $customer->id, 'status' => 'processing']);
        [$pendingItem] = $this->orderItems($pending, [['name' => 'TB-500 5mg', 'price' => 80, 'quantity' => 1]]);

        $this->postJson('/api/orders/'.$pending->id.'/return', $this->returnPayload('I changed my mind about this order.', $pendingItem))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('order');
    }

    public function test_a_second_open_request_is_rejected_until_the_first_is_rejected(): void
    {
        Sanctum::actingAs($customer = $this->customer());
        $order = $this->deliveredOrder(['user_id' => $customer->id]);
        [$item] = $this->orderItems($order, [['name' => 'BPC-157 10mg', 'price' => 100, 'quantity' => 1]]);

        $this->postJson('/api/orders/'.$order->id.'/return', $this->returnPayload('The vial arrived damaged in transit.', $item))->assertCreated();

        $this->postJson('/api/orders/'.$order->id.'/return', $this->returnPayload('The vial arrived damaged in transit.', $item))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('order');
    }

    public function test_customers_cannot_request_returns_on_other_customers_orders(): void
    {
        Sanctum::actingAs($this->customer());
        $order = $this->deliveredOrder();
        [$item] = $this->orderItems($order, [['name' => 'BPC-157 10mg', 'price' => 100, 'quantity' => 1]]);

        $this->postJson('/api/orders/'.$order->id.'/return', $this->returnPayload('The vial arrived damaged in transit.', $item))
            ->assertNotFound();
    }

    public function test_an_unknown_order_item_is_rejected(): void
    {
        Sanctum::actingAs($customer = $this->customer());
        $order = $this->deliveredOrder(['user_id' => $customer->id]);
        $this->orderItems($order, [['name' => 'BPC-157 10mg', 'price' => 100, 'quantity' => 1]]);

        $this->postJson('/api/orders/'.$order->id.'/return', [
            'reason' => 'The vial arrived damaged in transit.',
            'items' => [['order_item_id' => 99999, 'quantity' => 1]],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('items');
        $this->assertDatabaseCount('return_requests', 0);
    }

    public function test_requested_quantities_are_capped_at_what_is_still_returnable(): void
    {
        Sanctum::actingAs($customer = $this->customer());
        $order = $this->deliveredOrder(['user_id' => $customer->id]);
        [$item] = $this->orderItems($order, [['name' => 'BPC-157 10mg', 'price' => 100, 'quantity' => 1]]);

        $this->postJson('/api/orders/'.$order->id.'/return', [
            'reason' => 'I ordered two of these by mistake last week.',
            'items' => [['order_item_id' => $item->id, 'quantity' => 2]],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('items')
            ->assertJsonFragment(['Only 1 × BPC-157 10mg can still be returned.']);
        $this->assertDatabaseCount('return_requests', 0);
    }

    public function test_a_partial_return_keeps_the_order_delivered_and_partially_refunded(): void
    {
        Sanctum::actingAs($customer = $this->customer());
        $product = $this->product(['stock_quantity' => 10, 'low_stock_threshold' => 2]);
        $order = $this->deliveredOrder(['user_id' => $customer->id, 'total_amount' => 250]);
        [$kept, $returned] = $this->orderItems($order, [
            ['name' => 'TB-500 5mg', 'price' => 50, 'quantity' => 2],
            ['name' => 'BPC-157 10mg', 'price' => 100, 'quantity' => 1, 'product_id' => $product->id],
        ]);
        $this->saleMovement($order, $product, 10, 9);

        $this->postJson('/api/orders/'.$order->id.'/return', [
            'reason' => 'One vial arrived damaged in transit.',
            'items' => [['order_item_id' => $returned->id, 'quantity' => 1]],
        ])->assertCreated();
        $returnId = ReturnRequest::where('order_id', $order->id)->first()->id;

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        // No refund_amount sent: the server computes it from the returned lines.
        $this->patchJson('/api/orders/'.$order->id.'/returns/'.$returnId, [
            'decision' => 'approved', 'decision_notes' => 'Verified with the courier.',
        ])->assertOk()
            ->assertJsonPath('order.status', 'delivered')
            ->assertJsonPath('order.payment_status', 'partially_refunded');

        $this->assertDatabaseHas('return_requests', ['id' => $returnId, 'status' => 'approved', 'refund_amount' => 100]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'delivered', 'payment_status' => 'partially_refunded']);
        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $order->id, 'old_status' => 'delivered', 'new_status' => 'delivered',
        ]);
        // Only the returned quantity is restocked (the sale movement is a
        // record only — the return adds one unit to the product's stock).
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 11]);
    }

    public function test_a_second_partial_return_can_be_requested_and_approved_after_the_first(): void
    {
        Sanctum::actingAs($customer = $this->customer());
        $order = $this->deliveredOrder(['user_id' => $customer->id, 'total_amount' => 250]);
        [$kept, $first] = $this->orderItems($order, [
            ['name' => 'TB-500 5mg', 'price' => 50, 'quantity' => 2],
            ['name' => 'BPC-157 10mg', 'price' => 100, 'quantity' => 1],
        ]);

        $this->postJson('/api/orders/'.$order->id.'/return', [
            'reason' => 'One vial arrived damaged in transit.',
            'items' => [['order_item_id' => $first->id, 'quantity' => 1]],
        ])->assertCreated();
        $firstReturnId = ReturnRequest::where('order_id', $order->id)->first()->id;

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->patchJson('/api/orders/'.$order->id.'/returns/'.$firstReturnId, ['decision' => 'approved'])->assertOk();

        // The customer sends another request for one unit of the kept item.
        Sanctum::actingAs($customer);
        $this->postJson('/api/orders/'.$order->id.'/return', [
            'reason' => 'The second vial also arrived damaged.',
            'items' => [['order_item_id' => $kept->id, 'quantity' => 1]],
        ])->assertCreated();
        $secondReturnId = ReturnRequest::where('order_id', $order->id)->where('status', 'requested')->first()->id;

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->patchJson('/api/orders/'.$order->id.'/returns/'.$secondReturnId, ['decision' => 'approved'])
            ->assertOk()
            ->assertJsonPath('order.status', 'delivered')
            ->assertJsonPath('order.payment_status', 'partially_refunded');

        $this->assertDatabaseHas('return_requests', ['id' => $secondReturnId, 'status' => 'approved', 'refund_amount' => 50]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'delivered', 'payment_status' => 'partially_refunded']);

        // Returning the last unit completes the return: the order is cancelled
        // and the remaining balance (including shipping/COD) is refunded.
        Sanctum::actingAs($customer);
        $this->postJson('/api/orders/'.$order->id.'/return', [
            'reason' => 'Sending back the very last vial as well.',
            'items' => [['order_item_id' => $kept->id, 'quantity' => 1]],
        ])->assertCreated();
        $thirdReturnId = ReturnRequest::where('order_id', $order->id)->where('status', 'requested')->first()->id;

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->patchJson('/api/orders/'.$order->id.'/returns/'.$thirdReturnId, ['decision' => 'approved'])
            ->assertOk()
            ->assertJsonPath('order.status', 'cancelled')
            ->assertJsonPath('order.payment_status', 'refunded');

        $this->assertDatabaseHas('return_requests', ['id' => $thirdReturnId, 'status' => 'approved', 'refund_amount' => 100]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled', 'payment_status' => 'refunded']);
    }

    public function test_a_request_without_item_selection_cannot_be_approved(): void
    {
        Sanctum::actingAs($customer = $this->customer());
        $order = $this->deliveredOrder(['user_id' => $customer->id, 'total_amount' => 200]);
        $this->orderItems($order, [['name' => 'BPC-157 10mg', 'price' => 100, 'quantity' => 2]]);

        // A request created before item selection existed has no lines. The
        // customer must choose items — the admin cannot approve a blank one.
        $return = ReturnRequest::create([
            'order_id' => $order->id, 'user_id' => $customer->id,
            'reason' => 'The whole order arrived damaged in transit.',
            'status' => ReturnRequest::STATUS_REQUESTED,
        ]);

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->patchJson('/api/orders/'.$order->id.'/returns/'.$return->id, ['decision' => 'approved', 'refund_amount' => 200])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('decision');
        $this->assertDatabaseHas('return_requests', ['id' => $return->id, 'status' => 'requested']);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'delivered']);

        $this->patchJson('/api/orders/'.$order->id.'/returns/'.$return->id, ['decision' => 'rejected', 'decision_notes' => 'Please select the items you want to return.'])
            ->assertOk();
        $this->assertDatabaseHas('return_requests', ['id' => $return->id, 'status' => 'rejected']);
    }

    public function test_a_stale_request_without_items_is_superseded_when_the_customer_selects_items(): void
    {
        Sanctum::actingAs($customer = $this->customer());
        $order = $this->deliveredOrder(['user_id' => $customer->id, 'total_amount' => 200]);
        [$item] = $this->orderItems($order, [['name' => 'BPC-157 10mg', 'price' => 100, 'quantity' => 2]]);
        $stale = ReturnRequest::create([
            'order_id' => $order->id, 'user_id' => $customer->id,
            'reason' => 'I want to return one of the vials.',
            'status' => ReturnRequest::STATUS_REQUESTED,
        ]);

        $this->postJson('/api/orders/'.$order->id.'/return', [
            'reason' => 'Returning one vial that arrived damaged in transit.',
            'items' => [['order_item_id' => $item->id, 'quantity' => 1]],
        ])->assertCreated();

        $this->assertDatabaseHas('return_requests', [
            'id' => $stale->id, 'status' => 'rejected',
            'decision_notes' => 'Superseded: the customer submitted a new request with item selection.',
        ]);
        $this->assertDatabaseCount('return_request_items', 1);
        $this->assertDatabaseHas('return_requests', ['order_id' => $order->id, 'status' => 'requested']);
    }

    public function test_a_full_return_refunds_the_remaining_order_balance_including_shipping(): void
    {
        Sanctum::actingAs($customer = $this->customer());
        $order = $this->deliveredOrder(['user_id' => $customer->id, 'total_amount' => 235]);
        [$item] = $this->orderItems($order, [['name' => 'BPC-157 10mg', 'price' => 105, 'quantity' => 2]]);

        $this->postJson('/api/orders/'.$order->id.'/return', [
            'reason' => 'Both vials arrived damaged in transit.',
            'items' => [['order_item_id' => $item->id, 'quantity' => 2]],
        ])->assertCreated();
        $returnId = ReturnRequest::where('order_id', $order->id)->first()->id;

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        // No amount sent: a full return refunds the whole remaining balance,
        // including the AED 25 delivery charge recorded in the order total.
        $this->patchJson('/api/orders/'.$order->id.'/returns/'.$returnId, ['decision' => 'approved'])
            ->assertOk()
            ->assertJsonPath('order.status', 'cancelled')
            ->assertJsonPath('order.payment_status', 'refunded');

        $this->assertDatabaseHas('return_requests', ['id' => $returnId, 'status' => 'approved', 'refund_amount' => 235]);
    }

    public function test_a_full_return_cancels_the_order_and_refunds_everything(): void
    {
        Sanctum::actingAs($customer = $this->customer());
        $product = $this->product(['stock_quantity' => 10, 'low_stock_threshold' => 2]);
        $order = $this->deliveredOrder(['user_id' => $customer->id, 'total_amount' => 200]);
        [$item] = $this->orderItems($order, [
            ['name' => 'BPC-157 10mg', 'price' => 100, 'quantity' => 2, 'product_id' => $product->id],
        ]);
        $this->saleMovement($order, $product, 10, 8);

        $this->postJson('/api/orders/'.$order->id.'/return', [
            'reason' => 'Both vials arrived damaged in transit.',
            'items' => [['order_item_id' => $item->id, 'quantity' => 2]],
        ])->assertCreated();
        $returnId = ReturnRequest::where('order_id', $order->id)->first()->id;

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->patchJson('/api/orders/'.$order->id.'/returns/'.$returnId, [
            'decision' => 'approved', 'refund_amount' => 200, 'decision_notes' => 'Verified with the courier.',
        ])->assertOk()
            ->assertJsonPath('order.status', 'cancelled')
            ->assertJsonPath('order.payment_status', 'refunded');

        $this->assertDatabaseHas('return_requests', ['id' => $returnId, 'status' => 'approved', 'refund_amount' => 200]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled', 'shipping_status' => 'cancelled', 'payment_status' => 'refunded']);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 12]);
        $this->assertDatabaseHas('inventory_transactions', ['product_id' => $product->id, 'type' => 'return', 'reason' => 'Returned order '.$order->order_number, 'quantity_before' => 10, 'quantity_after' => 12]);
        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $order->id, 'old_status' => 'delivered', 'new_status' => 'cancelled',
        ]);
    }

    public function test_refund_approval_cannot_exceed_the_returned_items_value(): void
    {
        Sanctum::actingAs($customer = $this->customer());
        $order = $this->deliveredOrder(['user_id' => $customer->id, 'total_amount' => 250]);
        [$kept, $returned] = $this->orderItems($order, [
            ['name' => 'TB-500 5mg', 'price' => 50, 'quantity' => 2],
            ['name' => 'BPC-157 10mg', 'price' => 100, 'quantity' => 1],
        ]);

        $this->postJson('/api/orders/'.$order->id.'/return', [
            'reason' => 'One vial arrived damaged in transit.',
            'items' => [['order_item_id' => $returned->id, 'quantity' => 1]],
        ])->assertCreated();
        $returnId = ReturnRequest::where('order_id', $order->id)->first()->id;

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->patchJson('/api/orders/'.$order->id.'/returns/'.$returnId, ['decision' => 'approved', 'refund_amount' => 150])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('refund_amount');
        $this->assertDatabaseHas('return_requests', ['id' => $returnId, 'status' => 'requested']);
    }

    public function test_a_return_request_can_only_be_decided_once(): void
    {
        Sanctum::actingAs($customer = $this->customer());
        $order = $this->deliveredOrder(['user_id' => $customer->id]);
        [$item] = $this->orderItems($order, [['name' => 'BPC-157 10mg', 'price' => 100, 'quantity' => 1]]);

        $this->postJson('/api/orders/'.$order->id.'/return', $this->returnPayload('The vial arrived damaged in transit.', $item))->assertCreated();
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
     * Attach line items to an order, creating a product per line when none is
     * given (order_items.product_id is required).
     *
     * @param  array<int, array{name: string, price: float, quantity: int, product_id?: int}>  $items
     * @return list<OrderItem>
     */
    private function orderItems(Order $order, array $items): array
    {
        return array_map(function (array $item) use ($order): OrderItem {
            $product = isset($item['product_id'])
                ? Product::findOrFail($item['product_id'])
                : $this->product(['name' => $item['name'], 'price' => $item['price'], 'slug' => fake()->unique()->slug(), 'sku' => 'TEST-'.fake()->unique()->numerify('######')]);

            return OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'name' => $item['name'],
                'price' => $item['price'],
                'quantity' => $item['quantity'],
                'total_price' => $item['price'] * $item['quantity'],
            ]);
        }, $items);
    }

    private function returnPayload(string $reason, OrderItem $item, int $quantity = 1): array
    {
        return ['reason' => $reason, 'items' => [['order_item_id' => $item->id, 'quantity' => $quantity]]];
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
