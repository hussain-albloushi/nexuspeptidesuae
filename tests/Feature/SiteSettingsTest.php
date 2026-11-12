<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SiteSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_settings_endpoint_exposes_the_website_timezone(): void
    {
        $this->storeTimezone('Asia/Dubai');

        $this->getJson('/api/store-settings')
            ->assertOk()
            ->assertJsonPath('timezone', 'Asia/Dubai');
    }

    public function test_written_timestamps_use_the_website_clock_not_utc(): void
    {
        $this->travelTo(Carbon::parse('2026-06-15 12:00:00', 'UTC'));
        $this->storeTimezone('Asia/Dubai');

        // The request applies the website clock before any database write.
        $this->getJson('/api/store-settings')->assertOk();
        $user = User::factory()->create();

        $this->assertSame('Asia/Dubai', config('app.timezone'));
        $this->assertSame('Asia/Dubai', date_default_timezone_get());
        $this->assertSame('2026-06-15 16:00:00', (string) $user->getRawOriginal('created_at'));
        $this->assertSame('2026-06-15 16:00:00', (string) $user->getRawOriginal('updated_at'));
    }

    public function test_saving_settings_applies_the_clock_and_email_sender_immediately(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->postJson('/api/settings', [
            'timezone' => 'Asia/Tokyo',
            'mail_from_name' => 'Nexus Peptides Support',
            'mail_from_address' => 'support@nexuspeptides.com',
        ])->assertOk()->assertJsonPath('timezone', 'Asia/Tokyo');

        $this->assertDatabaseHas('settings', ['key' => 'timezone', 'value' => 'Asia/Tokyo']);
        $this->assertSame('Asia/Tokyo', config('app.timezone'));
        $this->assertSame('Asia/Tokyo', date_default_timezone_get());
        $this->assertSame('support@nexuspeptides.com', config('mail.from.address'));
        $this->assertSame('Nexus Peptides Support', config('mail.from.name'));
    }

    public function test_saving_a_new_timezone_stamps_the_change_with_the_new_clock(): void
    {
        $this->travelTo(Carbon::parse('2026-06-15 12:00:00', 'UTC'));
        $this->storeTimezone('UTC');
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->postJson('/api/settings', ['timezone' => 'Asia/Dubai'])->assertOk();

        $updatedAt = Setting::where('key', 'timezone')->firstOrFail()->getRawOriginal('updated_at');

        $this->assertSame('2026-06-15 16:00:00', (string) $updatedAt);
    }

    public function test_invalid_timezone_returns_422_without_saving(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->postJson('/api/settings', ['timezone' => 'Mars/Phobos'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('timezone');

        $this->assertDatabaseMissing('settings', ['value' => 'Mars/Phobos']);
    }

    public function test_invalid_sender_email_returns_422(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->postJson('/api/settings', ['mail_from_address' => 'not-an-email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('mail_from_address');
    }

    public function test_unauthenticated_request_cannot_save_settings(): void
    {
        $this->postJson('/api/settings', ['timezone' => 'Asia/Tokyo'])->assertUnauthorized();
    }

    private function storeTimezone(string $timezone): void
    {
        Setting::updateOrCreate(
            ['key' => 'timezone'],
            ['value' => $timezone, 'type' => 'string', 'is_secret' => false]
        );

        app(SiteSettings::class)->forget();
    }
}
