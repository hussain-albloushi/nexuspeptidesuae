<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_emails_a_reset_link_without_revealing_unknown_addresses(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'customer@example.com']);

        $this->postJson('/api/forgot-password', ['email' => 'customer@example.com'])
            ->assertOk()->assertJsonPath('message', 'If that email address is on file, a reset link has been sent.');
        $this->postJson('/api/forgot-password', ['email' => 'ghost@example.com'])
            ->assertOk()->assertJsonPath('message', 'If that email address is on file, a reset link has been sent.');

        Notification::assertSentTo($user, ResetPasswordLink::class);
        Notification::assertSentToTimes($user, ResetPasswordLink::class, 1);
    }

    public function test_reset_password_changes_the_password_and_revokes_existing_tokens(): void
    {
        Notification::fake();
        $user = User::factory()->create(['password' => 'old-password-123']);
        $user->createToken('auth-token');
        $this->postJson('/api/forgot-password', ['email' => $user->email])->assertOk();

        $token = null;
        Notification::assertSentTo($user, ResetPasswordLink::class, function (ResetPasswordLink $notification) use (&$token, $user): bool {
            $token = $notification->token;

            return $notification->email === $user->email;
        });

        $this->postJson('/api/reset-password', [
            'email' => $user->email, 'token' => $token,
            'password' => 'new-password-456', 'password_confirmation' => 'new-password-456',
        ])->assertOk()->assertJsonPath('message', 'Your password has been reset. You can now log in.');

        $this->assertTrue(Hash::check('new-password-456', $user->fresh()->password));
        $this->assertCount(0, $user->fresh()->tokens);
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'old-password-123'])->assertUnauthorized();
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'new-password-456'])->assertOk();
    }

    public function test_reset_with_an_invalid_token_fails(): void
    {
        $user = User::factory()->create(['password' => 'old-password-123']);

        $this->postJson('/api/reset-password', [
            'email' => $user->email, 'token' => 'wrong-token',
            'password' => 'new-password-456', 'password_confirmation' => 'new-password-456',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->assertTrue(Hash::check('old-password-123', $user->fresh()->password));
    }

    public function test_reset_with_a_mismatched_confirmation_fails_validation(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/reset-password', [
            'email' => $user->email, 'token' => 'any-token',
            'password' => 'new-password-456', 'password_confirmation' => 'different-password',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_forgot_and_reset_require_an_email(): void
    {
        $this->postJson('/api/forgot-password', [])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson('/api/reset-password', [])->assertUnprocessable()->assertJsonValidationErrors(['token', 'email', 'password']);
    }
}
