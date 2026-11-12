<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_a_customer_and_an_api_token(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Test Customer',
            'email' => 'customer@example.com',
            'password' => 'test-password',
            'password_confirmation' => 'test-password',
            'role' => 'admin',
        ]);

        $response->assertCreated()->assertJsonStructure(['user', 'access_token']);
        $this->assertDatabaseHas('users', ['email' => 'customer@example.com', 'role' => 'customer']);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_duplicate_registration_returns_422(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/register', [
            'name' => 'Duplicate Customer',
            'email' => $user->email,
            'password' => 'test-password',
            'password_confirmation' => 'test-password',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('users', 1);
    }

    public function test_login_returns_a_token_for_valid_credentials(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()->assertJsonPath('user.id', $user->id)->assertJsonStructure(['access_token']);

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_login_returns_422_for_missing_credentials(): void
    {
        $this->postJson('/api/login', [])->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_returns_401_for_incorrect_credentials(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'incorrect-password'])
            ->assertUnauthorized();
    }

    public function test_logout_deletes_only_the_current_token(): void
    {
        $user = User::factory()->create();
        $current = $user->createToken('current');
        $other = $user->createToken('other');

        $this->withToken($current->plainTextToken)->postJson('/api/logout')->assertOk();

        $this->assertModelMissing($current->accessToken);
        $this->assertModelExists($other->accessToken);
    }
}
