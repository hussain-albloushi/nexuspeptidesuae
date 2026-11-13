<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_update_their_name_and_email(): void
    {
        $user = User::factory()->create(['name' => 'Old Name', 'email' => 'old@example.test']);
        Sanctum::actingAs($user);

        $this->putJson('/api/me', [
            'name' => 'New Name',
            'email' => 'new@example.test',
        ])
            ->assertOk()
            ->assertJsonPath('name', 'New Name')
            ->assertJsonPath('email', 'new@example.test');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'new@example.test']);
    }

    public function test_keeping_your_own_email_is_allowed_but_another_users_is_not(): void
    {
        $user = User::factory()->create(['email' => 'mine@example.test']);
        User::factory()->create(['email' => 'taken@example.test']);
        Sanctum::actingAs($user);

        $this->putJson('/api/me', ['name' => 'Mine', 'email' => 'mine@example.test'])->assertOk();

        $this->putJson('/api/me', ['name' => 'Mine', 'email' => 'taken@example.test'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_changing_the_email_clears_the_verification(): void
    {
        $user = User::factory()->create(['email' => 'verified@example.test']);
        Sanctum::actingAs($user);

        $this->putJson('/api/me', ['name' => $user->name, 'email' => 'other@example.test'])->assertOk();

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_a_user_can_change_their_password_with_the_current_one(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/me/password', [
            'current_password' => 'password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertOk();

        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
    }

    public function test_a_wrong_current_password_or_weak_new_password_is_rejected(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/me/password', [
            'current_password' => 'not-my-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');

        $this->putJson('/api/me/password', [
            'current_password' => 'password',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_guests_cannot_update_the_account(): void
    {
        $this->putJson('/api/me', ['name' => 'Nope', 'email' => 'nope@example.test'])->assertUnauthorized();
        $this->putJson('/api/me/password', [])->assertUnauthorized();
    }
}
