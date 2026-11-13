<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\HelpCenterMessageConfirmation;
use App\Notifications\HelpCenterMessageReceived;
use App\Services\GoogleRecaptcha;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Address;
use Tests\TestCase;

class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, string> */
    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Aisha',
            'last_name' => 'Al Marri',
            'email' => 'aisha@example.com',
            'mobile' => '+971501234567',
            'subject' => 'Question about BPC-157',
            'message' => 'Do you have this peptide in stock?',
            'recaptcha_token' => 'test-token',
        ], $overrides);
    }

    protected function publishedSiteContent(): void
    {
        Content::create([
            'key' => 'site',
            'label' => 'Site',
            'type' => 'group',
            'data' => ['supportEmail' => 'support@nexuspeptides.test'],
            'is_active' => true,
            'sort_order' => 0,
        ]);
    }

    protected function enableRecaptcha(): void
    {
        Setting::create(['key' => 'recaptcha_enabled', 'value' => '1', 'type' => 'string', 'is_secret' => false]);
        Setting::create(['key' => 'recaptcha_site_key', 'value' => 'site-key', 'type' => 'string', 'is_secret' => false]);
        Setting::create(['key' => 'recaptcha_secret_key', 'value' => 'secret-key', 'type' => 'string', 'is_secret' => true]);
    }

    public function test_a_valid_submission_emails_the_support_inbox_and_the_customer(): void
    {
        Notification::fake();
        $this->publishedSiteContent();

        $response = $this->postJson('/api/contact', $this->payload());

        $response->assertOk()->assertJson(['message' => 'Your message has been sent to our support team. A copy is on its way to your inbox.']);

        Notification::assertSentOnDemand(
            HelpCenterMessageReceived::class,
            fn (HelpCenterMessageReceived $notification, array $channels, AnonymousNotifiable $notifiable): bool => $channels === ['mail'] && $notifiable->routes['mail'] === 'support@nexuspeptides.test'
        );
        Notification::assertSentOnDemand(
            HelpCenterMessageConfirmation::class,
            fn (HelpCenterMessageConfirmation $notification, array $channels, AnonymousNotifiable $notifiable): bool => $channels === ['mail'] && $notifiable->routes['mail'] === 'aisha@example.com'
        );
    }

    public function test_the_received_notification_carries_the_message_details(): void
    {
        Notification::fake();
        $this->publishedSiteContent();

        $this->postJson('/api/contact', $this->payload())->assertOk();

        Notification::assertSentOnDemand(
            HelpCenterMessageReceived::class,
            function (HelpCenterMessageReceived $notification, array $channels, object $notifiable): bool {
                return $channels === ['mail']
                    && $notifiable->routes['mail'] === 'support@nexuspeptides.test'
                    && $notification->message['first_name'] === 'Aisha'
                    && $notification->message['message'] === 'Do you have this peptide in stock?';
            }
        );
    }

    public function test_it_falls_back_to_the_configured_sender_when_no_support_email_is_published(): void
    {
        Notification::fake();
        config(['mail.from.address' => 'fallback@nexuspeptides.test']);

        $this->postJson('/api/contact', $this->payload())->assertOk();

        Notification::assertSentOnDemand(
            HelpCenterMessageReceived::class,
            fn (HelpCenterMessageReceived $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === 'fallback@nexuspeptides.test'
        );
    }

    public function test_required_fields_are_validated(): void
    {
        Notification::fake();
        $this->publishedSiteContent();

        $response = $this->postJson('/api/contact', $this->payload([
            'first_name' => '',
            'email' => 'not-an-email',
            'message' => '',
        ]));

        $response->assertStatus(422)->assertJsonValidationErrors(['first_name', 'email', 'message']);

        Notification::assertNothingSent();
    }

    public function test_recaptcha_is_not_required_when_the_service_is_unconfigured(): void
    {
        Notification::fake();
        Http::fake();
        $this->publishedSiteContent();

        $this->postJson('/api/contact', $this->payload(['recaptcha_token' => null]))->assertOk();

        Http::assertNothingSent();
    }

    public function test_submission_is_rejected_when_recaptcha_verification_fails(): void
    {
        Notification::fake();
        $this->publishedSiteContent();
        $this->enableRecaptcha();

        Http::fake([
            GoogleRecaptcha::VERIFY_URL => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']]),
        ]);

        $response = $this->postJson('/api/contact', $this->payload());

        $response->assertStatus(422)->assertJsonValidationErrors(['recaptcha']);
        Http::assertSent(fn ($request) => $request['secret'] === 'secret-key' && $request['response'] === 'test-token');
        Notification::assertNothingSent();
    }

    public function test_v3_submission_is_rejected_when_the_score_is_too_low(): void
    {
        Notification::fake();
        $this->publishedSiteContent();
        $this->enableRecaptcha();

        Http::fake([
            GoogleRecaptcha::VERIFY_URL => Http::response(['success' => true, 'score' => 0.1, 'action' => 'help_center_message']),
        ]);

        $this->postJson('/api/contact', $this->payload())->assertStatus(422);
        Notification::assertNothingSent();
    }

    public function test_v3_submission_is_rejected_when_the_action_does_not_match(): void
    {
        Notification::fake();
        $this->publishedSiteContent();
        $this->enableRecaptcha();

        Http::fake([
            GoogleRecaptcha::VERIFY_URL => Http::response(['success' => true, 'score' => 0.9, 'action' => 'login']),
        ]);

        $this->postJson('/api/contact', $this->payload())->assertStatus(422);
    }

    public function test_v3_submission_is_accepted_with_a_good_score(): void
    {
        Notification::fake();
        $this->publishedSiteContent();
        $this->enableRecaptcha();

        Http::fake([
            GoogleRecaptcha::VERIFY_URL => Http::response(['success' => true, 'score' => 0.9, 'action' => 'help_center_message']),
        ]);

        $this->postJson('/api/contact', $this->payload())->assertOk();
        Notification::assertCount(2);
    }

    public function test_a_v2_checkbox_response_without_a_score_is_accepted(): void
    {
        Notification::fake();
        $this->publishedSiteContent();
        $this->enableRecaptcha();

        Http::fake([
            GoogleRecaptcha::VERIFY_URL => Http::response(['success' => true, 'challenge_ts' => now()->toIso8601String()]),
        ]);

        $this->postJson('/api/contact', $this->payload())->assertOk();
    }

    public function test_the_secret_key_is_never_returned_by_the_settings_endpoint(): void
    {
        $this->enableRecaptcha();
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $settings = $this->getJson('/api/settings')->assertOk()->json();

        $this->assertArrayNotHasKey('recaptcha_secret_key', $settings);
        $this->assertTrue($settings['recaptcha_secret_key_set']);
        $this->assertSame('site-key', $settings['recaptcha_site_key']);
    }

    public function test_store_settings_exposes_the_public_site_key(): void
    {
        $this->enableRecaptcha();

        $body = $this->getJson('/api/store-settings')->assertOk()->json();

        $this->assertSame('site-key', $body['recaptchaKey']);
    }

    public function test_store_settings_hides_the_key_when_protection_is_disabled(): void
    {
        $this->enableRecaptcha();
        Setting::query()->where('key', 'recaptcha_enabled')->update(['value' => '0']);

        $body = $this->getJson('/api/store-settings')->assertOk()->json();

        $this->assertNull($body['recaptchaKey']);
    }

    public function test_the_dedicated_delivery_inbox_overrides_the_published_support_email(): void
    {
        Notification::fake();
        $this->publishedSiteContent();
        Setting::create(['key' => 'contact_delivery_email', 'value' => 'inbox@nexuspeptides.test', 'type' => 'string', 'is_secret' => false]);

        $this->postJson('/api/contact', $this->payload())->assertOk();

        Notification::assertSentOnDemand(
            HelpCenterMessageReceived::class,
            fn (HelpCenterMessageReceived $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === 'inbox@nexuspeptides.test'
        );
    }

    public function test_both_emails_render_and_address_their_recipients(): void
    {
        $this->publishedSiteContent();

        $this->postJson('/api/contact', $this->payload())->assertOk();

        $messages = $this->app['mailer']->getSymfonyTransport()->messages();
        $this->assertCount(2, $messages);

        $all = $messages->map(function (SentMessage $message): string {
            $recipients = implode(',', array_map(
                fn (Address $address): string => $address->getAddress(),
                $message->getEnvelope()->getRecipients()
            ));

            return $recipients.': '.$message->getOriginalMessage()->getHtmlBody().$message->getOriginalMessage()->getTextBody();
        })->implode("\n");

        $this->assertStringContainsString('support@nexuspeptides.test:', $all);
        $this->assertStringContainsString('aisha@example.com:', $all);
        $this->assertStringContainsString('Question about BPC-157', $all);
        $this->assertStringContainsString('Thank you, Aisha', $all);
    }
}
