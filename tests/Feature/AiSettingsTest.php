<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AiSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_never_expose_the_api_key(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        Setting::create(['key' => 'ai_api_key', 'value' => 'sk-super-secret', 'type' => 'string', 'is_secret' => true]);

        $response = $this->getJson('/api/ai/settings')->assertOk();

        $this->assertStringNotContainsString('sk-super-secret', (string) $response->getContent());
        $this->assertTrue($response->json('ai_api_key_set'));
    }

    public function test_saving_the_api_key_stores_it_as_secret_and_hides_it(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->postJson('/api/ai/settings', ['ai_api_key' => 'sk-new-secret-key', 'ai_model' => 'gpt-4o'])
            ->assertOk();

        $stored = Setting::where('key', 'ai_api_key')->first();
        $this->assertTrue((bool) $stored->is_secret);
        $this->assertSame('sk-new-secret-key', Crypt::decryptString($stored->value));

        $this->getJson('/api/ai/settings')->assertOk()
            ->assertJsonPath('ai_api_key_set', true)
            ->assertJsonPath('ai_model', 'gpt-4o');
    }

    public function test_saving_an_empty_key_preserves_the_existing_secret(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        Setting::create(['key' => 'ai_api_key', 'value' => 'sk-keep-me', 'type' => 'string', 'is_secret' => true]);

        $this->postJson('/api/ai/settings', ['ai_api_key' => '', 'ai_provider' => 'openrouter'])->assertOk();

        $this->assertSame('sk-keep-me', Crypt::decryptString(Setting::where('key', 'ai_api_key')->value('value')));
        $this->assertSame('openrouter', Setting::where('key', 'ai_provider')->value('value'));
    }

    public function test_thresholds_are_persisted_and_validated(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->postJson('/api/ai/settings', [
            'ai_low_stock_threshold' => 7,
            'ai_profit_margin_warning_threshold' => 18.5,
            'ai_temperature' => 1.5,
        ])->assertOk();

        $this->postJson('/api/ai/settings', ['ai_temperature' => 99])->assertUnprocessable();

        $this->getJson('/api/ai/settings')->assertOk()
            ->assertJsonPath('ai_low_stock_threshold', 7)
            ->assertJsonPath('ai_profit_margin_warning_threshold', 18.5)
            ->assertJsonPath('ai_temperature', 1.5);
    }

    public function test_settings_require_an_admin(): void
    {
        $this->getJson('/api/ai/settings')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/ai/settings')->assertForbidden();
        $this->postJson('/api/ai/settings', [])->assertForbidden();
    }
}
