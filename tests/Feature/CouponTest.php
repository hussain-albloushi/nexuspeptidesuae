<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CouponTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_and_update_a_coupon(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $response = $this->postJson('/api/coupons', [
            'code' => 'welcome10', 'type' => 'percentage', 'value' => 10, 'is_active' => true,
        ])->assertCreated()->assertJsonPath('code', 'WELCOME10');

        $this->putJson('/api/coupons/'.$response->json('id'), [
            'code' => 'WELCOME10', 'type' => 'percentage', 'value' => 15, 'is_active' => true,
        ])->assertOk();

        $this->assertDatabaseHas('coupons', ['code' => 'WELCOME10', 'value' => 15]);
    }

    public function test_percentage_above_100_returns_422(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->postJson('/api/coupons', [
            'code' => 'TOOMUCH', 'type' => 'percentage', 'value' => 101,
        ])->assertUnprocessable()->assertJsonValidationErrors('value');

        $this->assertDatabaseCount('coupons', 0);
    }

    public function test_exhausted_coupon_is_rejected_by_public_validation(): void
    {
        Coupon::create([
            'code' => 'USED', 'type' => 'fixed', 'value' => 10,
            'is_active' => true, 'usage_limit' => 1, 'usage_count' => 1,
        ]);

        $this->postJson('/api/coupons/validate', ['code' => 'used', 'orderTotal' => 100])
            ->assertOk()->assertJsonPath('ok', false)->assertJsonPath('discount', 0);
    }

    public function test_customer_cannot_create_a_coupon(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/coupons', [])->assertForbidden();

        $this->assertDatabaseCount('coupons', 0);
    }

    public function test_coupon_can_have_an_expiry_without_a_start_date(): void
    {
        $this->freezeTime();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->postJson('/api/coupons', [
            'code' => 'EXPIRES', 'type' => 'fixed', 'value' => 10,
            'starts_at' => null, 'expires_at' => now()->addDay()->toDateTimeString(),
        ])->assertCreated();

        $this->assertDatabaseHas('coupons', ['code' => 'EXPIRES', 'starts_at' => null]);
    }

    public function test_zero_discount_cap_is_preserved_in_public_validation(): void
    {
        Coupon::create([
            'code' => 'ZERO', 'type' => 'fixed', 'value' => 10, 'is_active' => true, 'max_discount' => 0,
        ]);

        $this->postJson('/api/coupons/validate', ['code' => 'ZERO', 'orderTotal' => 100])
            ->assertOk()->assertJsonPath('discount', 0)->assertJsonPath('maxDiscount', 0);
    }
}
