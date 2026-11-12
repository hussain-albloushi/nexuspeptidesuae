<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminSearchTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('orderQueries')]
    public function test_orders_can_be_found_by_number_or_customer(string $query): void
    {
        $customer = User::factory()->create(['name' => 'Alice Research', 'email' => 'alice@example.com']);
        $target = Order::create([
            'user_id' => $customer->id, 'order_number' => 'NP-ABC123',
            'subtotal' => 100, 'total_amount' => 105,
        ]);
        Order::create([
            'user_id' => User::factory()->create()->id, 'order_number' => 'NP-OTHER',
            'subtotal' => 100, 'total_amount' => 105,
        ]);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->getJson('/api/orders?'.http_build_query(['q' => $query]))
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $target->id);
    }

    public static function orderQueries(): array
    {
        return [
            'displayed number' => ['np-abc123'],
            'number copied with hash and whitespace' => ['  #NP-ABC123  '],
            'customer name' => ['ALICE RESEARCH'],
            'customer email' => ['alice@example.com'],
        ];
    }

    public function test_matching_orders_are_found_before_the_recent_order_limit_is_applied(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $target = Order::create([
            'user_id' => $user->id, 'order_number' => 'NP-LEGACY',
            'subtotal' => 100, 'total_amount' => 105, 'created_at' => '2026-01-01 00:00:00',
        ]);
        $orders = [];
        for ($index = 0; $index < 200; $index++) {
            $orders[] = [
                'user_id' => $user->id, 'order_number' => 'RECENT-'.$index,
                'subtotal' => 100, 'total_amount' => 105, 'created_at' => '2026-01-02 00:00:00',
            ];
        }
        Order::insert($orders);
        Sanctum::actingAs($user);

        $this->getJson('/api/orders?q=NP-LEGACY')
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $target->id);
    }

    #[DataProvider('productQueries')]
    public function test_product_search_matches_names_and_literal_skus(string $query): void
    {
        $product = Product::create(['name' => 'BPC-157', 'slug' => 'bpc-157', 'sku' => 'NX_100', 'price' => 100]);
        Product::create(['name' => 'Unrelated', 'slug' => 'unrelated', 'sku' => 'NXX100', 'price' => 100]);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->getJson('/api/products?'.http_build_query(['q' => $query]))
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $product->id);
    }

    public static function productQueries(): array
    {
        return ['name' => ['bPc-157'], 'sku with literal underscore' => ['  nx_100  ']];
    }

    public function test_customer_search_does_not_include_matching_administrators(): void
    {
        $customer = User::factory()->create(['name' => 'Alice Customer', 'email' => 'alice@example.com']);
        User::factory()->create(['name' => 'Other Customer']);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'name' => 'Alice Admin']));

        $this->getJson('/api/customers?q=alice')
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $customer->id);
    }

    #[DataProvider('searchEndpoints')]
    public function test_invalid_query_arrays_return_422(string $endpoint): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->getJson($endpoint.'?q[]=invalid')->assertUnprocessable()->assertJsonValidationErrors('q');
    }

    #[DataProvider('searchEndpoints')]
    public function test_search_requires_admin_access(string $endpoint): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson($endpoint.'?q=alice')->assertForbidden();
    }

    public static function searchEndpoints(): array
    {
        return ['orders' => ['/api/orders'], 'products' => ['/api/products'], 'customers' => ['/api/customers']];
    }

    public function test_empty_search_keeps_the_existing_product_listing(): void
    {
        Product::create(['name' => 'BPC-157', 'slug' => 'bpc-157', 'sku' => 'BPC-157', 'price' => 100]);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->getJson('/api/products?q=')->assertOk()->assertJsonCount(1);
    }
}
