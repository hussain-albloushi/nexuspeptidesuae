<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AiReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_creates_a_stored_report_and_show_returns_it(): void
    {
        $this->travelTo(Carbon::parse('2026-11-13 12:00:00', 'UTC'));
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $customer = User::factory()->create();

        $product = Product::create([
            'name' => 'Report Product', 'slug' => 'report-product', 'sku' => 'RP-RPT',
            'price' => 100, 'cost_price' => 30, 'stock_quantity' => 10, 'is_active' => true,
        ]);
        $order = Order::create([
            'user_id' => $customer->id, 'order_number' => 'RPT-1',
            'subtotal' => 100, 'total_amount' => 100, 'status' => 'delivered', 'payment_status' => 'paid',
        ]);
        $order->forceFill(['created_at' => '2026-11-10 10:00:00'])->save();
        OrderItem::create([
            'order_id' => $order->id, 'product_id' => $product->id, 'name' => $product->name,
            'price' => 100, 'quantity' => 1, 'total_price' => 100,
        ]);

        $response = $this->postJson('/api/ai/reports/generate', ['type' => 'monthly_report'])
            ->assertCreated();

        $reportId = $response->json('report.id');
        $this->assertNotNull($reportId);
        $this->assertDatabaseHas('ai_reports', ['id' => $reportId, 'type' => 'monthly_report']);

        $show = $this->getJson("/api/ai/reports/{$reportId}")->assertOk();
        $this->assertStringContainsString('Monthly Business Report', (string) $show->json('report.content'));
        $this->assertStringContainsString('Revenue', (string) $show->json('report.content'));

        $this->assertDatabaseCount('ai_reports', 1);
    }

    public function test_generate_rejects_unknown_report_types(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->postJson('/api/ai/reports/generate', ['type' => 'not_a_report'])->assertUnprocessable();
    }

    public function test_report_listing_and_schedule_toggle(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->getJson('/api/ai/reports')->assertOk()
            ->assertJsonStructure(['reports', 'types', 'scheduled']);

        $this->postJson('/api/ai/reports/schedule', ['report' => 'weekly_report', 'enabled' => false])
            ->assertOk()->assertJsonPath('enabled', false);

        $this->assertSame('0', Setting::where('key', 'ai_schedule_weekly_report')->value('value'));
    }

    public function test_reports_require_an_admin(): void
    {
        $this->getJson('/api/ai/reports')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/ai/reports')->assertForbidden();
        $this->postJson('/api/ai/reports/generate', ['type' => 'monthly_report'])->assertForbidden();
    }
}
