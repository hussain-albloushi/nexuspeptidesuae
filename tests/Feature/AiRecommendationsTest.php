<?php

namespace Tests\Feature;

use App\Models\AiRecommendation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AiRecommendationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_regenerate_creates_and_deduplicates_recommendations(): void
    {
        $this->travelTo(Carbon::parse('2026-11-13 12:00:00', 'UTC'));
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $customer = User::factory()->create();

        // A high-profit product that sells → triggers "Promote High-Profit Products"
        $product = Product::create([
            'name' => 'Margin Star', 'slug' => 'margin-star', 'sku' => 'MS-1',
            'price' => 100, 'cost_price' => 10, 'stock_quantity' => 3, 'low_stock_threshold' => 5, 'is_active' => true,
        ]);
        $order = $this->order($customer, 'AI-REC-1', 300, 'delivered', '2026-11-10 10:00:00');
        OrderItem::create([
            'order_id' => $order->id, 'product_id' => $product->id, 'name' => $product->name,
            'price' => 100, 'quantity' => 3, 'total_price' => 300,
        ]);

        $first = $this->postJson('/api/ai/recommendations/regenerate?period=30d')->assertOk();
        $this->assertGreaterThan(0, $first->json('created'));

        // Second run over identical data must not duplicate rows
        $second = $this->postJson('/api/ai/recommendations/regenerate?period=30d')->assertOk();
        $this->assertSame(0, $second->json('created'));

        $this->getJson('/api/ai/recommendations')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta', 'counts', 'categories']);
    }

    public function test_recommendations_can_be_reviewed_completed_and_noted(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $recommendation = AiRecommendation::create([
            'title' => 'Test rec', 'description' => 'desc', 'category' => 'Marketing Opportunities',
            'priority' => 'high', 'status' => 'new', 'reasoning' => 'why',
            'suggested_action' => 'do this', 'expected_impact' => 'more sales',
            'supporting_data' => '{}', 'relevant_products' => [1], 'relevant_categories' => ['Peptides'],
            'stability_signature' => 'test-signature-'.uniqid(),
        ]);

        $this->putJson("/api/ai/recommendations/{$recommendation->id}/status", ['status' => 'reviewed'])
            ->assertOk()->assertJsonPath('recommendation.status', 'reviewed');

        $this->putJson("/api/ai/recommendations/{$recommendation->id}/status", ['status' => 'completed'])
            ->assertOk()->assertJsonPath('recommendation.status', 'completed');

        $this->putJson("/api/ai/recommendations/{$recommendation->id}/note", ['internal_notes' => 'Done in sprint 14'])
            ->assertOk()->assertJsonPath('recommendation.internal_notes', 'Done in sprint 14');

        $this->putJson("/api/ai/recommendations/{$recommendation->id}/status", ['status' => 'bogus'])->assertUnprocessable();
    }

    public function test_recommendations_require_an_admin(): void
    {
        $this->getJson('/api/ai/recommendations')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/ai/recommendations')->assertForbidden();
        $this->postJson('/api/ai/recommendations/regenerate')->assertForbidden();
    }

    private function order(User $customer, string $number, float $total, string $status, string $date): Order
    {
        $order = Order::create([
            'user_id' => $customer->id, 'order_number' => $number,
            'subtotal' => $total, 'total_amount' => $total, 'status' => $status, 'payment_status' => 'paid',
        ]);
        $order->forceFill(['created_at' => $date])->save();

        return $order;
    }
}
