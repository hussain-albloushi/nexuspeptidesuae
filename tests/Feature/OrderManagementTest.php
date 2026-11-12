<?php

namespace Tests\Feature;

use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrderManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_filters_search_dates_and_pagination_combine_before_the_page_limit(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $customer = User::factory()->create(['name' => 'Alice Research']);
        $order = $this->order(['user_id' => $customer->id, 'created_at' => '2026-11-10 23:59:59']);
        $this->order(['status' => 'cancelled', 'created_at' => '2026-11-10 10:00:00']);
        $this->order(['created_at' => '2026-11-11 00:00:00']);

        $this->getJson('/api/orders?'.http_build_query([
            'q' => 'ALICE', 'status' => 'pending', 'payment_status' => 'pending',
            'start_date' => '2026-11-10', 'end_date' => '2026-11-10', 'per_page' => 10,
        ]))->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $order->id)
            ->assertJsonPath('data.0.allowed_transitions', ['processing', 'cancelled']);
    }

    public function test_paginated_orders_are_not_limited_to_the_first_200(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $customer = User::factory()->create();
        $rows = [];
        for ($index = 0; $index < 205; $index++) {
            $rows[] = ['user_id' => $customer->id, 'order_number' => 'NP-'.$index, 'subtotal' => 100, 'total_amount' => 100, 'created_at' => '2026-11-10 10:00:00'];
        }
        Order::insert($rows);

        $this->getJson('/api/orders?per_page=100&page=3')->assertOk()->assertJsonPath('total', 205)
            ->assertJsonPath('current_page', 3)->assertJsonCount(5, 'data');
    }

    #[DataProvider('sortableColumns')]
    public function test_data_columns_sort_in_both_directions(string $column, bool $secondFirst): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $first = $this->order(['user_id' => User::factory()->create(['name' => 'Zoe'])->id, 'order_number' => 'NP-Z', 'created_at' => '2026-11-10 10:00:00']);
        $second = $this->order(['user_id' => User::factory()->create(['name' => 'Alice'])->id, 'order_number' => 'NP-A', 'total_amount' => 20, 'status' => 'processing', 'payment_status' => 'paid', 'created_at' => '2026-11-11 10:00:00']);

        $this->getJson('/api/orders?per_page=10&sort='.$column.'&direction=asc')->assertOk()->assertJsonPath('data.0.id', $secondFirst ? $second->id : $first->id);
        $this->getJson('/api/orders?per_page=10&sort='.$column.'&direction=desc')->assertOk()->assertJsonPath('data.0.id', $secondFirst ? $first->id : $second->id);
    }

    public static function sortableColumns(): array
    {
        return [['id', false], ['order_number', true], ['customer', true], ['created_at', false], ['status', false], ['payment_status', true], ['total_amount', true]];
    }

    public function test_invalid_filters_and_sort_columns_return_422(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->getJson('/api/orders?status=unknown&payment_status=unknown&sort=DROP&direction=invalid&start_date=2026-11-11&end_date=2026-10-01&per_page=1000')
            ->assertUnprocessable()->assertJsonValidationErrors(['status', 'payment_status', 'sort', 'direction', 'end_date', 'per_page']);
    }

    public function test_details_include_items_customer_shipping_payment_and_chronological_history(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $order = $this->order(['shipping_address' => json_encode(['city' => 'Dubai']), 'tracking_number' => 'TRACK-100']);
        $product = Product::create(['name' => 'Product', 'sku' => 'P', 'slug' => 'p', 'price' => 100]);
        $order->items()->create(['product_id' => $product->id, 'name' => 'Product snapshot', 'price' => 100, 'quantity' => 1, 'total_price' => 100]);
        $order->statusHistory()->create(['old_status' => 'pending', 'new_status' => 'processing', 'notes' => 'Packed']);

        $this->getJson('/api/orders/'.$order->id)->assertOk()->assertJsonPath('items.0.name', 'Product snapshot')
            ->assertJsonPath('status_history.0.notes', 'Packed')->assertJsonPath('tracking_number', 'TRACK-100')
            ->assertJsonPath('user.id', $order->user_id)->assertJsonPath('payment_status', 'pending');
    }

    #[DataProvider('validTransitions')]
    public function test_status_changes_record_history_and_shipping_state(string $from, string $to): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $order = $this->order(['status' => $from]);

        $this->patchJson('/api/orders/'.$order->id.'/status', ['expected_status' => $from, 'status' => $to, 'notes' => 'Admin review', 'tracking_number' => 'TRACK-100'])
            ->assertOk()->assertJsonPath('order.status', $to);

        $this->assertDatabaseHas('order_status_history', ['order_id' => $order->id, 'old_status' => $from, 'new_status' => $to, 'notes' => 'Admin review']);
        if (in_array($to, ['shipped', 'delivered', 'cancelled'], true)) {
            $this->assertDatabaseHas('orders', ['id' => $order->id, 'shipping_status' => $to]);
        }
        if ($to === 'shipped') {
            $this->assertDatabaseHas('orders', ['id' => $order->id, 'tracking_number' => 'TRACK-100']);
        }
    }

    public static function validTransitions(): array
    {
        return [['pending', 'processing'], ['processing', 'shipped'], ['shipped', 'delivered'], ['pending', 'cancelled'], ['processing', 'cancelled'], ['shipped', 'cancelled'], ['delivered', 'cancelled']];
    }

    public function test_stale_or_disallowed_status_changes_return_422_without_history(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $order = $this->order();

        $this->patchJson('/api/orders/'.$order->id.'/status', ['expected_status' => 'processing', 'status' => 'shipped'])->assertUnprocessable()->assertJsonValidationErrors('orders');
        $this->patchJson('/api/orders/'.$order->id.'/status', ['expected_status' => 'pending', 'status' => 'delivered'])->assertUnprocessable()->assertJsonValidationErrors('status');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending']);
        $this->assertDatabaseCount('order_status_history', 0);
    }

    public function test_cancellation_requires_a_reason_and_restores_recorded_unshipped_stock_only_once(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $order = $this->order(['payment_status' => 'paid']);
        $product = Product::create(['name' => 'Product', 'sku' => 'P', 'slug' => 'p', 'price' => 100, 'stock_quantity' => 8]);
        InventoryTransaction::create(['product_id' => $product->id, 'quantity_before' => 10, 'quantity_after' => 8, 'type' => 'sale', 'reason' => 'Order '.$order->order_number]);

        $this->patchJson('/api/orders/'.$order->id.'/status', ['expected_status' => 'pending', 'status' => 'cancelled'])->assertUnprocessable()->assertJsonValidationErrors('notes');
        $this->patchJson('/api/orders/'.$order->id.'/status', ['expected_status' => 'pending', 'status' => 'cancelled', 'notes' => 'Customer request'])->assertOk();
        $this->patchJson('/api/orders/'.$order->id.'/status', ['expected_status' => 'cancelled', 'status' => 'cancelled', 'notes' => 'Retry'])->assertUnprocessable();

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 10]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'paid']);
        $this->assertDatabaseHas('inventory_transactions', ['product_id' => $product->id, 'type' => 'return', 'quantity_before' => 8, 'quantity_after' => 10]);
        $this->assertDatabaseCount('order_status_history', 1);
    }

    public function test_cancelling_shipped_orders_does_not_restock_goods_still_with_the_customer(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $order = $this->order(['status' => 'shipped']);
        $product = Product::create(['name' => 'Product', 'sku' => 'P', 'slug' => 'p', 'price' => 100, 'stock_quantity' => 8]);
        InventoryTransaction::create(['product_id' => $product->id, 'quantity_before' => 10, 'quantity_after' => 8, 'type' => 'sale', 'reason' => 'Order '.$order->order_number]);

        $this->patchJson('/api/orders/'.$order->id.'/status', ['expected_status' => 'shipped', 'status' => 'cancelled', 'notes' => 'Cancelled shipment'])->assertOk();

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 8]);
        $this->assertDatabaseCount('inventory_transactions', 1);
    }

    public function test_bulk_status_updates_are_atomic_and_accept_only_valid_transitions(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $first = $this->order();
        $second = $this->order(['status' => 'processing']);

        $this->postJson('/api/orders/bulk-status', ['orders' => [
            ['id' => $first->id, 'expected_status' => 'pending'], ['id' => $second->id, 'expected_status' => 'processing'],
        ], 'status' => 'shipped'])->assertUnprocessable();

        $this->assertDatabaseHas('orders', ['id' => $first->id, 'status' => 'pending']);
        $this->assertDatabaseHas('orders', ['id' => $second->id, 'status' => 'processing']);
        $this->assertDatabaseCount('order_status_history', 0);

        $this->postJson('/api/orders/bulk-status', ['orders' => [
            ['id' => $first->id, 'expected_status' => 'pending'], ['id' => $second->id, 'expected_status' => 'processing'],
        ], 'status' => 'cancelled', 'notes' => 'Customer request'])->assertOk();
        $this->assertDatabaseCount('order_status_history', 2);
    }

    public function test_bulk_validation_rejects_duplicate_and_missing_orders(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $order = $this->order();

        $this->postJson('/api/orders/bulk-status', ['orders' => [
            ['id' => $order->id, 'expected_status' => 'pending'], ['id' => $order->id, 'expected_status' => 'pending'],
        ], 'status' => 'processing'])->assertUnprocessable()->assertJsonValidationErrors('orders.0.id');
        $this->postJson('/api/orders/bulk-status', ['orders' => [['id' => 999, 'expected_status' => 'pending']], 'status' => 'processing'])
            ->assertUnprocessable()->assertJsonValidationErrors('orders.0.id');
    }

    public function test_csv_export_respects_filters_and_escapes_spreadsheet_formulas(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->order(['user_id' => User::factory()->create(['name' => '=DANGEROUS()'])->id, 'order_number' => 'MATCH']);
        $this->order(['order_number' => 'IGNORE', 'status' => 'cancelled']);

        $response = $this->get('/api/orders/export?format=csv&status=pending')->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $content = $response->streamedContent();

        $this->assertStringContainsString('MATCH', $content);
        $this->assertStringNotContainsString('IGNORE', $content);
        $this->assertStringContainsString("'=DANGEROUS()", $content);
    }

    public function test_selected_exports_include_only_requested_orders_and_print_view_escapes_html(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $order = $this->order(['order_number' => '<script>alert(1)</script>']);
        $this->order(['order_number' => 'IGNORE']);

        $this->get('/api/orders/export?'.http_build_query(['format' => 'print', 'ids' => [$order->id]]))->assertOk()
            ->assertSee('Print / Save as PDF')->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('IGNORE');
    }

    public function test_invoice_uses_saved_item_prices_and_totals(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $order = $this->order(['subtotal' => 200, 'tax_amount' => 10, 'total_amount' => 210]);
        $product = Product::create(['name' => 'Current product', 'sku' => 'P', 'slug' => 'p', 'price' => 500]);
        $order->items()->create(['product_id' => $product->id, 'name' => 'Purchased product', 'price' => 100, 'quantity' => 2, 'total_price' => 200]);

        $this->get('/api/orders/'.$order->id.'/invoice')->assertOk()->assertSee('Purchased product')->assertSee('210.00')->assertDontSee('500.00');
    }

    public function test_new_admin_endpoints_reject_guests_and_customers(): void
    {
        $order = $this->order();
        $this->getJson('/api/orders/export')->assertUnauthorized();
        $this->getJson('/api/orders/'.$order->id.'/invoice')->assertUnauthorized();
        $this->patchJson('/api/orders/'.$order->id.'/status', [])->assertUnauthorized();
        $this->postJson('/api/orders/bulk-status', [])->assertUnauthorized();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/orders/export')->assertForbidden();
        $this->getJson('/api/orders/'.$order->id.'/invoice')->assertForbidden();
        $this->patchJson('/api/orders/'.$order->id.'/status', [])->assertForbidden();
        $this->postJson('/api/orders/bulk-status', [])->assertForbidden();
        $this->assertDatabaseCount('order_status_history', 0);
    }

    public function test_delivered_cod_orders_keep_pending_payment_until_collection_is_confirmed(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $order = $this->order(['status' => 'shipped', 'payment_method' => 'cod', 'payment_status' => 'pending']);

        $this->patchJson('/api/orders/'.$order->id.'/status', ['expected_status' => 'shipped', 'status' => 'delivered'])->assertOk();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'delivered', 'payment_status' => 'pending']);
        $this->assertDatabaseMissing('order_status_history', ['order_id' => $order->id, 'notes' => 'Cash-on-delivery payment marked paid under the delivery settlement rule.']);
    }

    public function test_collect_payment_records_cod_payment_as_paid_for_delivered_orders_only(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $order = $this->order(['status' => 'delivered', 'payment_method' => 'cod', 'payment_status' => 'pending']);

        $this->patchJson('/api/orders/'.$order->id.'/payment', ['confirmed' => true, 'expected_payment_status' => 'pending'])
            ->assertOk()->assertJsonPath('order.payment_status', 'paid');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'paid']);
        $this->assertDatabaseHas('order_status_history', ['order_id' => $order->id, 'old_status' => 'delivered', 'new_status' => 'delivered']);
    }

    public function test_collect_payment_before_delivery_fails_without_changes(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $order = $this->order(['status' => 'shipped', 'payment_method' => 'cod', 'payment_status' => 'pending']);

        $this->patchJson('/api/orders/'.$order->id.'/payment', ['confirmed' => true, 'expected_payment_status' => 'pending'])
            ->assertUnprocessable()->assertJsonValidationErrors('payment');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'pending']);
        $this->assertDatabaseCount('order_status_history', 0);
    }

    public function test_collect_payment_for_already_paid_orders_fails_without_changes(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $order = $this->order(['status' => 'delivered', 'payment_method' => 'cod', 'payment_status' => 'paid']);

        $this->patchJson('/api/orders/'.$order->id.'/payment', ['confirmed' => true, 'expected_payment_status' => 'pending'])
            ->assertUnprocessable()->assertJsonValidationErrors('payment');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'paid']);
        $this->assertDatabaseCount('order_status_history', 0);
    }

    private function order(array $attributes = []): Order
    {
        $order = Order::create(array_merge([
            'user_id' => User::factory()->create()->id, 'order_number' => 'NP-'.fake()->unique()->numerify('########'),
            'status' => 'pending', 'payment_status' => 'pending', 'subtotal' => 100, 'total_amount' => 100,
        ], $attributes));
        if (isset($attributes['created_at'])) {
            $order->forceFill(['created_at' => $attributes['created_at']])->save();
        }

        return $order;
    }
}
