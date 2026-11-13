<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\WelcomeEmail;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_sends_a_single_welcome_email_with_verification_link(): void
    {
        Notification::fake();

        $this->postJson('/api/register', [
            'name' => 'Test Customer', 'email' => 'customer@example.com',
            'password' => 'secret-password', 'password_confirmation' => 'secret-password',
        ])->assertCreated()->assertJsonPath('user.email', 'customer@example.com');

        $user = User::where('email', 'customer@example.com')->firstOrFail();
        Notification::assertSentTo($user, WelcomeEmail::class);
        Notification::assertNotSentTo($user, VerifyEmail::class);
    }

    public function test_verification_link_marks_the_email_verified(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(30), ['id' => $user->id, 'hash' => sha1($user->email)]);

        $this->get($url)->assertRedirect('/my-account?verified=1');

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_verification_link_with_a_tampered_hash_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(30), ['id' => $user->id, 'hash' => 'tampered']);

        $this->get($url)->assertRedirect('/my-account?verified=invalid');

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_verification_link_with_an_expired_signature_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->subMinute(), ['id' => $user->id, 'hash' => sha1($user->email)]);

        $this->get($url)->assertRedirect('/my-account?verified=invalid');

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_resend_sends_a_new_link_for_unverified_users(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/email/verification-notification')->assertOk();

        Notification::assertSentTo($user, VerifyEmail::class);
        Notification::assertNotSentTo($user, WelcomeEmail::class);
    }

    public function test_resend_does_nothing_for_verified_users(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/email/verification-notification')->assertOk()->assertJsonPath('message', 'Your email address is already verified.');

        Notification::assertNothingSent();
    }

    public function test_resend_requires_authentication(): void
    {
        $this->postJson('/api/email/verification-notification')->assertUnauthorized();
    }
}
