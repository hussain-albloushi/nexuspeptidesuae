<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SavedAddressTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_save_an_address(): void
    {
        $this->putJson('/api/me/address', ['saved_address' => $this->address()])
            ->assertUnauthorized();
    }

    public function test_customer_can_save_and_retrieve_billing_details(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->putJson('/api/me/address', ['saved_address' => $this->address()])
            ->assertOk()
            ->assertJsonPath('saved_address.name', 'Test Customer')
            ->assertJsonPath('saved_address.city', 'Dubai');

        $this->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('saved_address.name', 'Test Customer')
            ->assertJsonPath('saved_address.email', 'customer@example.com')
            ->assertJsonPath('saved_address.zip', '12345');
    }

    public function test_saving_an_address_updates_the_previous_one(): void
    {
        $user = User::factory()->create();
        $user->update(['saved_address' => $this->address(['city' => 'Sharjah'])]);
        Sanctum::actingAs($user);

        $this->putJson('/api/me/address', ['saved_address' => $this->address(['city' => 'Ajman'])])
            ->assertOk()
            ->assertJsonPath('saved_address.city', 'Ajman');

        $this->assertSame('Ajman', $user->fresh()->saved_address['city']);
    }

    public function test_extra_keys_are_not_stored(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/me/address', ['saved_address' => $this->address(['admin' => true, 'role' => 'admin'])])
            ->assertOk();

        $saved = $user->fresh()->saved_address;
        $this->assertArrayNotHasKey('admin', $saved);
        $this->assertArrayNotHasKey('role', $saved);
    }

    public function test_invalid_billing_details_return_422(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->putJson('/api/me/address', ['saved_address' => ['country' => 'United Arab Emirates']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['saved_address.name', 'saved_address.email', 'saved_address.address']);

        $this->putJson('/api/me/address', ['saved_address' => $this->address(['email' => 'not-an-email'])])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['saved_address.email']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function address(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test Customer',
            'email' => 'customer@example.com',
            'phone' => '0501234567',
            'address' => '123 Test Street',
            'zip' => '12345',
            'city' => 'Dubai',
            'country' => 'United Arab Emirates',
        ], $overrides);
    }
}
