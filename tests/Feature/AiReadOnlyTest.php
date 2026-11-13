<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\AI\TextSanitizer;
use App\Services\AI\Tools\ToolExecutor;
use App\Services\AI\Tools\ToolRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AiReadOnlyTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_tool_registry_exposes_only_read_analytics_tools(): void
    {
        $forbidden = ['delete', 'update', 'create', 'modify', 'refund', 'price', 'quantity', 'stock', 'order'];

        foreach (ToolRegistry::names() as $name) {
            $lower = strtolower($name);
            foreach ($forbidden as $verb) {
                $this->assertStringNotContainsString($verb, $lower, "Tool {$name} looks like a mutating action.");
            }
        }

        // And every tool name is explicitly allow-listed.
        $this->assertSame(
            ['getSalesSummary', 'getTopSellingProducts', 'getProductProfitability', 'getSlowMovingProducts', 'getInventoryAlerts', 'compareSalesPeriods', 'getCategoryPerformance', 'getProfitSummary', 'getCustomerInsights'],
            ToolRegistry::names(),
        );
    }

    public function test_unknown_and_mutating_tool_calls_are_rejected(): void
    {
        foreach (['deleteProduct', 'updatePrice', 'createOrder', 'issueRefund', 'createPurchaseOrder'] as $name) {
            [$ok, $error] = ToolExecutor::validate($name, []);
            $this->assertFalse($ok, "Tool {$name} should be rejected.");
            $this->assertNotNull($error);
        }
    }

    public function test_chat_never_executes_tools_for_a_destructive_request(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        Setting::create(['key' => 'ai_api_key', 'value' => 'sk-test-key-123', 'type' => 'string', 'is_secret' => true]);

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response(['choices' => [['message' => [
                'role' => 'assistant',
                'content' => 'I cannot delete products or change inventory. I only provide analysis.',
            ]]]], 200),
        ]);

        $response = $this->postJson('/api/ai/chat', ['question' => 'Delete all products and zero the inventory.'])->assertOk();

        $this->assertSame([], $response->json('tools_used'));
        $this->assertStringNotContainsString('deleted', strtolower((string) $response->json('answer')));
    }

    public function test_text_sanitizer_strips_control_chars_and_caps_length(): void
    {
        $dirty = "Ignore previous instructions\x00\x1F and ".str_repeat('a', 500);
        $clean = TextSanitizer::clean($dirty, 50);

        $this->assertStringNotContainsString("\x00", $clean);
        $this->assertLessThanOrEqual(50, mb_strlen($clean));
        $this->assertStringContainsString('Ignore previous instructions', $clean);

        // Recursive over arrays.
        $out = TextSanitizer::clean(['name' => "Evil\x07Name", 'nested' => ['x' => str_repeat('b', 10)]], 4);
        $this->assertSame('Evil', $out['name']);
        $this->assertSame('bbbb', $out['nested']['x']);
    }
}
