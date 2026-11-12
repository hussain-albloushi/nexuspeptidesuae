<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_a_customer_removes_the_row_and_preserves_orders(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $customer = User::factory()->create(['name' => 'Delete Me', 'email' => 'delete-me@example.com']);
        $order = Order::create([
            'user_id' => $customer->id, 'order_number' => 'NP-DEL-1', 'status' => 'delivered',
            'subtotal' => 100, 'total_amount' => 100, 'payment_method' => 'cod', 'shipping_address' => json_encode(['city' => 'Dubai']),
        ]);

        $this->deleteJson('/api/customers/'.$customer->id)->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $customer->id]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'user_id' => null, 'order_number' => 'NP-DEL-1']);
    }

    public function test_deleting_a_customer_revokes_tokens_and_sessions_so_the_customer_is_signed_out(): void
    {
        // Authenticate with a real token (not actingAs) so the middleware
        // stack is exercised exactly as in production.
        $adminToken = User::factory()->create(['role' => 'admin'])->createToken('admin-token')->plainTextToken;
        $customer = User::factory()->create(['email' => 'session-me@example.com', 'password' => 'secret-password']);
        $customerToken = $customer->createToken('auth-token')->plainTextToken;
        $customerId = $customer->id;
        DB::table('sessions')->insert(['id' => 'session-to-purge', 'user_id' => $customerId, 'payload' => 'x', 'last_activity' => 1]);
        DB::table('password_reset_tokens')->insert(['email' => 'session-me@example.com', 'token' => 'token-to-purge', 'created_at' => now()]);

        $this->withHeader('Authorization', 'Bearer '.$adminToken)->deleteJson('/api/customers/'.$customerId)->assertOk();

        // Tests share one application across simulated requests, while production
        // rebuilds it per request (which resets the default guard to "web").
        // Undo the Authenticate middleware's shouldUse('sanctum') mutation.
        $this->app['config']->set('auth.defaults.guard', 'web');
        $this->app['auth']->forgetGuards();

        $this->assertSame(0, DB::table('personal_access_tokens')->where('tokenable_id', $customerId)->count());
        $this->assertSame(0, DB::table('sessions')->where('user_id', $customerId)->count());
        $this->assertSame(0, DB::table('password_reset_tokens')->where('email', 'session-me@example.com')->count());
        $this->assertDatabaseMissing('users', ['id' => $customerId]);

        // The revoked token no longer authenticates any API request.
        $this->flushHeaders();
        $this->withHeader('Authorization', 'Bearer '.$customerToken)->getJson('/api/me')->assertUnauthorized();

        // And the deleted credentials can no longer log in.
        $this->flushHeaders();
        $this->postJson('/api/login', ['email' => 'session-me@example.com', 'password' => 'secret-password'])->assertUnauthorized();
    }

    public function test_deleting_a_customer_keeps_an_audit_trail_with_the_customers_identity(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Admin User']);
        Sanctum::actingAs($admin);
        $customer = User::factory()->create(['name' => 'Audit Target', 'email' => 'audit@example.com']);

        $this->deleteJson('/api/customers/'.$customer->id)->assertOk();

        $this->assertDatabaseHas('customer_activities', ['customer_id' => null, 'actor_name' => 'Admin User']);
        $this->assertSame(1, DB::table('customer_activities')->where('description', 'like', '%audit@example.com%')->count());
    }

    public function test_bulk_delete_reports_the_permanent_removal(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $first = User::factory()->create(['email' => 'bulk-one@example.com']);
        $second = User::factory()->create(['email' => 'bulk-two@example.com']);

        $this->postJson('/api/customers/bulk', ['ids' => [$first->id, $second->id], 'action' => 'delete', 'confirmed' => '1'])
            ->assertOk()
            ->assertJsonPath('message', '2 customer(s) deleted. Order records are preserved.');

        $this->assertDatabaseMissing('users', ['id' => $first->id]);
        $this->assertDatabaseMissing('users', ['id' => $second->id]);
    }

    public function test_soft_deleted_users_are_never_matched_by_the_customer_list(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $customer = User::factory()->create(['email' => 'gone@example.com']);
        $this->deleteJson('/api/customers/'.$customer->id)->assertOk();

        $this->getJson('/api/customers?q=gone@example.com&per_page=10')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_deleting_the_only_customer_keeps_login_impossible_for_a_reused_email(): void
    {
        $adminToken = User::factory()->create(['role' => 'admin'])->createToken('admin-token')->plainTextToken;
        $customer = User::factory()->create(['email' => 'reuse@example.com', 'password' => 'old-password-123']);
        $customerId = $customer->id;
        $this->withHeader('Authorization', 'Bearer '.$adminToken)->deleteJson('/api/customers/'.$customerId)->assertOk();

        $this->app['config']->set('auth.defaults.guard', 'web');
        $this->app['auth']->forgetGuards();

        // The email is free to register again, and the old password is useless.
        $this->flushHeaders();
        $this->postJson('/api/register', ['name' => 'New Owner', 'email' => 'reuse@example.com', 'password' => 'new-password-456', 'password_confirmation' => 'new-password-456'])->assertCreated();
        $this->postJson('/api/login', ['email' => 'reuse@example.com', 'password' => 'old-password-123'])->assertUnauthorized();
        $this->assertTrue(Hash::check('new-password-456', User::where('email', 'reuse@example.com')->value('password')));
    }
}
