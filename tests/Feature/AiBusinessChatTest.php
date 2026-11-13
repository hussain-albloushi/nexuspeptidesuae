<?php

namespace Tests\Feature;

use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\Setting;
use App\Models\User;
use App\Services\AI\Tools\ToolRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AiBusinessChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_requires_an_admin(): void
    {
        $this->postJson('/api/ai/chat', ['question' => 'How is revenue?'])->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/ai/chat', ['question' => 'How is revenue?'])->assertForbidden();
    }

    public function test_chat_returns_422_when_ai_is_not_configured(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->postJson('/api/ai/chat', ['question' => 'How much revenue this month?'])
            ->assertUnprocessable()
            ->assertJsonPath('error', 'ai_not_configured');
    }

    public function test_chat_validates_question_payload(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->postJson('/api/ai/chat', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('question');
    }

    public function test_chat_persists_conversation_and_answers_with_tools(): void
    {
        $this->travelTo(Carbon::parse('2026-11-13 12:00:00', 'UTC'));
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        Setting::create(['key' => 'ai_api_key', 'value' => 'sk-test-key-123', 'type' => 'string', 'is_secret' => true]);

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::sequence()
                ->push(['choices' => [['message' => [
                    'role' => 'assistant',
                    'content' => null,
                    'tool_calls' => [
                        ['id' => 'call_1', 'type' => 'function', 'function' => ['name' => 'getSalesSummary', 'arguments' => json_encode(['start' => '2026-10-14', 'end' => '2026-11-13'])]],
                    ],
                ]]]], 200)
                ->push(['choices' => [['message' => [
                    'role' => 'assistant',
                    'content' => 'Revenue for the last 30 days is AED 0.00 (orders: 0). No data was found in the range.',
                ]]]], 200),
        ]);

        $response = $this->postJson('/api/ai/chat', ['question' => 'How much revenue this month?'])->assertOk();

        $this->assertStringContainsString('Revenue', (string) $response->json('answer'));
        $this->assertSame(['getSalesSummary'], $response->json('tools_used'));

        $conversation = AiChatConversation::findOrFail($response->json('conversation_id'));
        $this->assertSame('How much revenue this month?', $conversation->title);
        $this->assertDatabaseHas('ai_chat_messages', ['conversation_id' => $conversation->id, 'role' => 'user']);

        $assistant = AiChatMessage::where('conversation_id', $conversation->id)->where('role', 'assistant')->first();
        $this->assertNotNull($assistant);
        $this->assertSame(['getSalesSummary'], $assistant->tool_calls);
        $this->assertNotNull($assistant->grounding);
    }

    public function test_chat_sends_tools_as_a_json_array(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        Setting::create(['key' => 'ai_api_key', 'value' => 'sk-test-key-123', 'type' => 'string', 'is_secret' => true]);

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response(['choices' => [['message' => [
                'role' => 'assistant',
                'content' => 'Revenue looks stable.',
            ]]]], 200),
        ]);

        $this->postJson('/api/ai/chat', ['question' => 'How is revenue?'])->assertOk();

        Http::assertSent(function (HttpRequest $request): bool {
            $tools = $request['tools'] ?? null;

            return is_array($tools)
                && array_is_list($tools)
                && count($tools) === count(ToolRegistry::names())
                && ($tools[0]['type'] ?? null) === 'function';
        });
    }

    public function test_chat_reports_provider_errors_without_leaking_the_api_key(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        Setting::create(['key' => 'ai_api_key', 'value' => 'sk-or-v1-secret-key-value', 'type' => 'string', 'is_secret' => true]);

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response(['error' => [
                'message' => 'Invalid model for key sk-or-v1-secret-key-value',
                'code' => 400,
            ]], 400),
        ]);

        $response = $this->postJson('/api/ai/chat', ['question' => 'How is revenue?'])
            ->assertStatus(502)
            ->assertJsonPath('error', 'ai_provider_error')
            ->assertJsonPath('status', 400);

        $detail = (string) $response->json('detail');
        $this->assertStringContainsString('Invalid model', $detail);
        $this->assertStringNotContainsString('sk-or-v1-secret-key-value', $detail);
    }

    public function test_chat_rejects_out_of_scope_tool_calls_gracefully(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        Setting::create(['key' => 'ai_api_key', 'value' => 'sk-test-key-123', 'type' => 'string', 'is_secret' => true]);

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::sequence()
                ->push(['choices' => [['message' => [
                    'role' => 'assistant',
                    'content' => null,
                    'tool_calls' => [
                        ['id' => 'call_bad', 'type' => 'function', 'function' => ['name' => 'dropAllTables', 'arguments' => '{}']],
                    ],
                ]]]], 200)
                ->push(['choices' => [['message' => [
                    'role' => 'assistant',
                    'content' => 'I cannot execute that action.',
                ]]]], 200),
        ]);

        $this->postJson('/api/ai/chat', ['question' => 'Delete all products please'])
            ->assertOk()
            ->assertJsonPath('answer', 'I cannot execute that action.');
    }

    public function test_chat_enforces_the_daily_token_limit(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        Setting::create(['key' => 'ai_api_key', 'value' => 'sk-test-key-123', 'type' => 'string', 'is_secret' => true]);
        Setting::create(['key' => 'ai_daily_token_limit', 'value' => '10', 'type' => 'string', 'is_secret' => false]);

        $conversation = AiChatConversation::create(['user_id' => auth()->id(), 'title' => 'Prior']);
        AiChatMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'earlier',
            'tokens_used' => 10,
        ]);

        $this->postJson('/api/ai/chat', ['question' => 'How is revenue?'])
            ->assertStatus(429)
            ->assertJsonPath('error', 'token_limit_reached');
    }

    public function test_chat_history_is_scoped_to_the_owner(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $owner = User::factory()->create(['role' => 'admin']);
        AiChatConversation::create(['user_id' => $owner->id, 'title' => 'Owner conversation']);
        $stranger = User::factory()->create(['role' => 'admin']);

        $this->assertDatabaseHas('ai_chat_conversations', ['title' => 'Owner conversation']);

        // The acting user (not $owner) cannot delete $owner's conversation.
        $this->deleteJson('/api/ai/chats/1')->assertNotFound();
    }
}
