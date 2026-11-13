<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_uses_real_orders_and_excludes_cancelled_sales(): void
    {
        $this->travelTo(Carbon::parse('2026-11-11 12:00:00', 'UTC'));
        $customer = User::factory()->create(['created_at' => '2026-11-01 10:00:00']);
        User::factory()->create(['created_at' => now()]);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $paid = $this->order($customer, 'PAID', 105, 'delivered', 'paid', '2026-11-11 10:00:00');
        $pending = $this->order($customer, 'PENDING', 50, 'pending', 'pending', '2026-11-11 11:00:00');
        $cancelled = $this->order($customer, 'CANCELLED', 500, 'cancelled', 'paid', '2026-11-11 11:30:00');
        $this->order($customer, 'PREVIOUS', 35, 'delivered', 'paid', '2026-11-04 10:00:00');
        $this->order($customer, 'OLD', 999, 'delivered', 'paid', '2026-10-01 10:00:00');
        $product = Product::create(['name' => 'Research Product', 'slug' => 'research-product', 'sku' => 'RP', 'price' => 50]);
        foreach ([[$paid, 2, 100], [$pending, 1, 50], [$cancelled, 10, 500]] as [$order, $quantity, $total]) {
            OrderItem::create([
                'order_id' => $order->id, 'product_id' => $product->id, 'name' => $product->name,
                'price' => 50, 'quantity' => $quantity, 'total_price' => $total,
            ]);
        }

        $this->getJson('/api/dashboard?days=7')->assertOk()
            ->assertJsonPath('period.start', '2026-11-05')
            ->assertJsonPath('period.end', '2026-11-11')
            ->assertJsonPath('metrics.order_value.value', 155)
            ->assertJsonPath('metrics.paid_sales.value', 105)
            ->assertJsonPath('metrics.paid_sales.previous', 35)
            ->assertJsonPath('metrics.paid_sales.change_percent', 200)
            ->assertJsonPath('metrics.orders.value', 2)
            ->assertJsonFragment(['status' => 'cancelled', 'count' => 1])
            ->assertJsonPath('metrics.customers.value', 1)
            ->assertJsonCount(7, 'trend')
            ->assertJsonPath('trend.0.booked', 0)
            ->assertJsonPath('trend.6.booked', 155)
            ->assertJsonPath('trend.6.paid', 105)
            ->assertJsonPath('attention.to_process', 1)
            ->assertJsonPath('attention.awaiting_payment', 1)
            ->assertJsonPath('top_products.0.units', 3)
            ->assertJsonPath('top_products.0.sales', 150)
            ->assertJsonPath('recent_orders.0.order_number', 'CANCELLED')
            ->assertJsonPath('recent_orders.0.user.name', $customer->name);
    }

    public function test_stock_alerts_include_only_active_products_requiring_restock(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        foreach ([
            ['LOW', 3, true], ['OUT', 0, true], ['HEALTHY', 10, true], ['INACTIVE', 0, false],
        ] as [$sku, $stock, $active]) {
            Product::create([
                'name' => $sku, 'slug' => strtolower($sku), 'sku' => $sku, 'price' => 100,
                'stock_quantity' => $stock, 'low_stock_threshold' => 5, 'is_active' => $active,
            ]);
        }

        $this->getJson('/api/dashboard')->assertOk()
            ->assertJsonPath('attention.low_stock', 1)
            ->assertJsonPath('attention.out_of_stock', 1)
            ->assertJsonCount(2, 'stock_alerts')
            ->assertJsonPath('stock_alerts.0.sku', 'OUT')
            ->assertJsonPath('stock_alerts.1.sku', 'LOW');
    }

    public function test_empty_dashboard_returns_zeroes_and_complete_daily_series(): void
    {
        $this->travelTo(Carbon::parse('2026-11-11 12:00:00', 'UTC'));
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->getJson('/api/dashboard?days=30')->assertOk()
            ->assertJsonPath('metrics.orders.value', 0)
            ->assertJsonPath('metrics.order_value.change_percent', null)
            ->assertJsonCount(30, 'trend')
            ->assertJsonPath('trend.0.booked', 0)
            ->assertJsonPath('trend.29.paid', 0)
            ->assertJsonCount(0, 'recent_orders')
            ->assertJsonCount(0, 'top_products')
            ->assertJsonCount(0, 'recent_customers');
    }

    public function test_new_orders_are_visible_on_the_next_refresh(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $customer = User::factory()->create();
        $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('metrics.orders.value', 0);
        $this->order($customer, 'NEW', 100, 'pending', 'pending', now()->toDateTimeString());

        $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('metrics.orders.value', 1);
    }

    public function test_invalid_reporting_period_returns_422(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->getJson('/api/dashboard?days=365')->assertUnprocessable()->assertJsonValidationErrors('days');
    }

    public function test_notifications_show_global_stock_and_payment_issues_and_clear_after_resolution(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $customer = User::factory()->create();
        $failed = $this->order($customer, 'FAILED', 100, 'pending', 'failed', now()->subDays(100)->toDateTimeString());
        $this->order($customer, 'UNPAID', 50, 'delivered', 'pending', now()->toDateTimeString());
        $this->order($customer, 'CANCELLED', 50, 'cancelled', 'failed', now()->toDateTimeString());
        $product = Product::create([
            'name' => 'Low stock', 'slug' => 'low-stock', 'sku' => 'LOW', 'price' => 100,
            'stock_quantity' => 5, 'low_stock_threshold' => 5, 'is_active' => true,
        ]);
        Product::create([
            'name' => 'Unavailable', 'slug' => 'unavailable', 'sku' => 'OUT', 'price' => 100,
            'stock_quantity' => 0, 'is_active' => true,
        ]);
        Product::create([
            'name' => 'Inactive', 'slug' => 'inactive', 'sku' => 'INACTIVE', 'price' => 100,
            'stock_quantity' => 0, 'is_active' => false,
        ]);

        $this->getJson('/api/dashboard/notifications')->assertOk()
            ->assertJsonPath('attention.to_process', 1)
            ->assertJsonPath('attention.failed_payments', 1)
            ->assertJsonPath('attention.awaiting_payment', 1)
            ->assertJsonPath('attention.low_stock', 1)
            ->assertJsonPath('attention.out_of_stock', 1);

        $product->update(['stock_quantity' => 20]);
        $failed->update(['status' => 'delivered', 'payment_status' => 'paid']);

        $this->getJson('/api/dashboard/notifications')->assertOk()
            ->assertJsonPath('attention.low_stock', 0)
            ->assertJsonPath('attention.failed_payments', 0)
            ->assertJsonPath('attention.to_process', 0);
    }

    public function test_empty_notifications_return_zero_counts(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->getJson('/api/dashboard/notifications')->assertOk()->assertJsonPath('attention', [
            'to_process' => 0, 'awaiting_payment' => 0, 'failed_payments' => 0,
            'low_stock' => 0, 'out_of_stock' => 0,
        ]);
    }

    public function test_notifications_require_an_admin(): void
    {
        $this->getJson('/api/dashboard/notifications')->assertUnauthorized();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/dashboard/notifications')->assertForbidden();
    }

    public function test_customers_cannot_view_dashboard_data(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/dashboard')->assertForbidden();
    }

    public function test_guests_cannot_view_dashboard_data(): void
    {
        $this->getJson('/api/dashboard')->assertUnauthorized();
    }

    private function order(User $customer, string $number, float $total, string $status, string $payment, string $date): Order
    {
        $order = Order::create([
            'user_id' => $customer->id, 'order_number' => $number, 'subtotal' => $total,
            'total_amount' => $total, 'status' => $status, 'payment_status' => $payment,
        ]);

        $order->forceFill(['created_at' => $date])->save();

        return $order;
    }
}
