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

class AiProfitIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_pnl_arithmetic_does_not_double_count_vat(): void
    {
        $this->travelTo(Carbon::parse('2026-11-13 12:00:00', 'UTC'));
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $customer = User::factory()->create();

        $product = Product::create([
            'name' => 'Profit Product', 'slug' => 'profit-product', 'sku' => 'PP-1',
            'price' => 105, 'cost_price' => 40, 'stock_quantity' => 50, 'is_active' => true,
        ]);

        $order = Order::create([
            'user_id' => $customer->id, 'order_number' => 'P&L-1',
            'subtotal' => 100, 'tax_amount' => 5, 'discount_amount' => 10, 'shipping_amount' => 20,
            'cod_fee' => 0, 'total_amount' => 115, 'status' => 'delivered', 'payment_status' => 'paid',
        ]);
        $order->forceFill(['created_at' => '2026-11-10 10:00:00'])->save();

        OrderItem::create([
            'order_id' => $order->id, 'product_id' => $product->id, 'name' => $product->name,
            'price' => 100, 'quantity' => 2, 'total_price' => 200,
        ]);

        // Note: subtotal in orders table is used as merchandise base for the test.
        $response = $this->getJson('/api/ai/profit/pnl?period=30d')->assertOk();

        $pnl = $response->json('pnl');

        // Gross revenue = subtotal + shipping + cod = 100 + 20 = 120
        $this->assertEquals(120.0, $pnl['gross_revenue']);
        // Net sales = gross − discounts (VAT is informational, never double-counted)
        $this->assertEquals(110.0, $pnl['net_sales_ex_vat']);
        $this->assertEquals(5.0, $pnl['tax_collected']);
        // COGS = 2 × 40 = 80 → gross profit = 110 − 80 = 30
        $this->assertEquals(80.0, $pnl['cost_of_goods_sold']);
        $this->assertEquals(30.0, $pnl['gross_profit']);
        // No expenses recorded in this test → net profit is flagged, not fabricated
        $this->assertFalse($pnl['expense_data_complete']);
        $this->assertNull($pnl['net_operating_profit']);
        $this->assertNotEmpty($pnl['note']);
    }

    public function test_profit_endpoints_require_an_admin(): void
    {
        $this->getJson('/api/ai/profit/pnl')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/ai/profit/pnl')->assertForbidden();
        $this->getJson('/api/ai/profit/products')->assertForbidden();
        $this->getJson('/api/ai/profit/categories')->assertForbidden();
    }
}
