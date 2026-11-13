<?php

namespace Tests\Feature;

use App\Models\EmailTemplate;
use App\Models\User;
use App\Notifications\WelcomeEmail;
use App\Services\EmailTemplates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmailTemplatesTest extends TestCase
{
    use RefreshDatabase;

    protected function actingAsAdmin(): User
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        return $admin;
    }

    /** @return array{subject: string, greeting: string|null, lines: array<int, string>, action: array{label: string, url: string}|null, salutation: string|null} */
    protected function resolveWelcome(string $key = 'welcome'): array
    {
        [$defaults, $context] = WelcomeEmail::sample()->parts('Aisha');

        return app(EmailTemplates::class)->resolve($key, $defaults, $context);
    }

    public function test_non_admins_cannot_reach_the_template_api(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/email-templates')->assertForbidden();
        $this->putJson('/api/email-templates/welcome', ['is_active' => true])->assertForbidden();
    }

    public function test_the_index_lists_all_five_templates_with_built_in_previews(): void
    {
        $this->actingAsAdmin();

        $templates = $this->getJson('/api/email-templates')->assertOk()->json('templates');

        $this->assertCount(5, $templates);
        $this->assertSame('welcome', $templates[0]['key']);
        $this->assertFalse($templates[0]['overridden']);
        $this->assertSame('Welcome to Nexus Peptides', $templates[0]['preview']['subject']);
        $this->assertSame('Welcome aboard, Aisha!', $templates[0]['preview']['greeting']);
    }

    public function test_a_saved_override_replaces_wording_and_substitutes_known_placeholders(): void
    {
        $this->actingAsAdmin();

        $this->putJson('/api/email-templates/welcome', [
            'subject' => 'Hello {name} — your custom subject',
            'body' => "First custom line\nToken stays: {order_number}",
            'action_label' => 'Go shopping',
            'is_active' => true,
        ])->assertOk();

        $parts = $this->resolveWelcome();

        $this->assertSame('Hello Aisha — your custom subject', $parts['subject']);
        $this->assertSame(['First custom line', 'Token stays: {order_number}'], $parts['lines']);
        $this->assertSame('Go shopping', $parts['action']['label']);
        // The button URL is code-generated and unaffected by overrides.
        $this->assertStringEndsWith('/peptide-shop', $parts['action']['url']);
        // Untouched fields fall back to the built-in wording.
        $this->assertSame('Welcome aboard, Aisha!', $parts['greeting']);
    }

    public function test_reverting_restores_the_built_in_wording(): void
    {
        $this->actingAsAdmin();

        EmailTemplate::create(['key' => 'welcome', 'label' => 'Welcome email', 'subject' => 'Custom', 'is_active' => true]);

        $this->deleteJson('/api/email-templates/welcome')->assertOk();

        $this->assertSame('Welcome to Nexus Peptides', $this->resolveWelcome()['subject']);
        $this->assertDatabaseMissing('email_templates', ['key' => 'welcome']);
    }

    public function test_sending_a_test_email_uses_the_real_delivery_pipeline(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/email-templates/welcome/test', ['email' => 'recipient@example.com'])
            ->assertOk()
            ->assertJson(['message' => 'A sample Welcome email test email is on its way to recipient@example.com.']);

        $messages = $this->app['mailer']->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $this->assertStringContainsString('Welcome aboard, there!', $messages->first()->getOriginalMessage()->getTextBody());
    }

    public function test_blank_templates_and_invalid_addresses_are_rejected_before_sending(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/email-templates/not-a-key/test', ['email' => 'recipient@example.com'])->assertNotFound();
        $this->postJson('/api/email-templates/welcome/test', ['email' => 'not-an-email'])->assertStatus(422);
    }

    public function test_smtp_settings_save_apply_to_runtime_config_and_keep_the_password_secret(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/settings', [
            'mail_transport' => 'smtp',
            'mail_host' => 'mail.example.test',
            'mail_port' => 2525,
            'mail_scheme' => 'tls',
            'mail_username' => 'bot@example.test',
            'mail_password' => 'sup3r-s3cret',
        ])->assertOk();

        $this->assertSame('mail.example.test', Config::get('mail.mailers.smtp.host'));
        $this->assertSame(2525, Config::get('mail.mailers.smtp.port'));
        $this->assertSame('sup3r-s3cret', Config::get('mail.mailers.smtp.password'));

        $settings = $this->getJson('/api/settings')->assertOk()->json();
        $this->assertTrue($settings['mail_password_set']);
        $this->assertArrayNotHasKey('mail_password', $settings);
        $this->assertDatabaseHas('settings', ['key' => 'mail_password', 'is_secret' => true]);

        // Empty secret submissions keep the stored password.
        $this->postJson('/api/settings', ['mail_password' => ''])->assertOk();
        $this->assertSame('sup3r-s3cret', Config::get('mail.mailers.smtp.password'));
    }

    public function test_invalid_mail_values_are_rejected(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/settings', ['mail_transport' => 'carrier-pigeon'])->assertStatus(422);
        $this->postJson('/api/settings', ['mail_port' => 99999])->assertStatus(422);
        $this->assertDatabaseMissing('settings', ['key' => 'mail_transport']);
    }
}
