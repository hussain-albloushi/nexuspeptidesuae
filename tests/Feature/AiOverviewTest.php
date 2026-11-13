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

class AiOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_overview_returns_twelve_kpis_with_comparisons(): void
    {
        $this->travelTo(Carbon::parse('2026-11-13 12:00:00', 'UTC'));
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $customer = User::factory()->create();

        $product = Product::create([
            'name' => 'Research Product', 'slug' => 'research-product', 'sku' => 'RP-1',
            'price' => 50, 'cost_price' => 20, 'stock_quantity' => 100, 'is_active' => true,
        ]);

        $current = $this->order($customer, 'AI-CURRENT', 100, 'delivered', 'paid', '2026-11-10 10:00:00');
        $previous = $this->order($customer, 'AI-PREVIOUS', 50, 'delivered', 'paid', '2026-10-10 10:00:00');
        $this->order($customer, 'AI-CANCELLED', 999, 'cancelled', 'paid', '2026-11-11 10:00:00');

        foreach ([[$current, 2, 100], [$previous, 1, 50]] as [$order, $qty, $total]) {
            OrderItem::create([
                'order_id' => $order->id, 'product_id' => $product->id, 'name' => $product->name,
                'price' => $product->price, 'quantity' => $qty, 'total_price' => $total,
            ]);
        }

        $response = $this->getJson('/api/ai/overview?period=30d')->assertOk()
            ->assertJsonCount(12, 'kpis');

        $kpis = collect($response->json('kpis'))->keyBy('label');

        $this->assertEquals(100.0, $kpis['Revenue']['value']);
        $this->assertEquals(50.0, $kpis['Revenue']['previous']);
        $this->assertEquals(100.0, $kpis['Revenue']['delta_percent']);
        $this->assertEquals('up', $kpis['Revenue']['trend']);

        // Orders KPI counts all placed orders in the window (same convention as the main dashboard).
        $this->assertEquals(2, $kpis['Orders']['value']);
        $this->assertEquals(1, $kpis['Orders']['previous']);

        $this->assertEquals(2, $kpis['Units Sold']['value']);
        $this->assertEquals(1, $kpis['Units Sold']['previous']);

        // Gross profit: net sales 100 − COGS (2 × 20) = 60
        $this->assertEquals(60.0, $kpis['Gross Profit']['value']);

        $this->assertArrayHasKey('analysis_generated_at', $response->json());
        $this->assertNotEmpty($response->json('summary'));
    }

    public function test_overview_requires_an_admin(): void
    {
        $this->getJson('/api/ai/overview')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/ai/overview')->assertForbidden();
    }

    /** @param array<string, mixed> $extra */
    private function order(User $customer, string $number, float $total, string $status, string $payment, string $date): Order
    {
        $order = Order::create([
            'user_id' => $customer->id, 'order_number' => $number,
            'subtotal' => $total, 'total_amount' => $total,
            'status' => $status, 'payment_status' => $payment,
        ]);
        $order->forceFill(['created_at' => $date])->save();

        return $order;
    }
}
