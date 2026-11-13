<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Expense;
use App\Models\Order;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierPurchaseOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminGlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function actingAsAdmin(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_search_returns_matching_records_from_every_section_with_deep_links(): void
    {
        $this->actingAsAdmin();

        $customer = User::factory()->create(['role' => 'customer', 'name' => 'Zephyr Labs', 'email' => 'zephyr@example.com']);
        $order = Order::create(['user_id' => $customer->id, 'order_number' => 'NP-ZEPHYR-1', 'subtotal' => 10, 'total_amount' => 12]);
        $category = Category::create(['name' => 'Zephyr Range', 'slug' => 'zephyr-range']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Zephyr Compound', 'slug' => 'zephyr-compound', 'sku' => 'ZEPHYR-10', 'price' => 99, 'is_active' => true]);
        Coupon::create(['code' => 'ZEPHYR10', 'type' => 'percentage', 'value' => 10, 'is_active' => true]);
        $supplier = Supplier::create([
            'supplier_code' => 'SUP-ZEPHYR',
            'legal_name' => 'Zephyr Supplies Ltd',
            'contact_person' => 'Zephyr Contact',
            'email' => 'sales@zephyr.test',
            'city' => 'Dubai',
            'address' => 'Zephyr Warehouse 1',
        ]);
        SupplierPurchaseOrder::create(['supplier_id' => $supplier->id, 'po_number' => 'PO-ZEPHYR-7', 'total_amount' => 500, 'status' => 'pending']);
        Expense::create(['name' => 'Zephyr courier fee', 'category' => 'shipping', 'date' => '2026-11-01', 'amount' => 50]);

        $results = $this->getJson('/api/global-search?q=zephyr')->assertOk()->json('results');
        $byType = collect($results)->groupBy('type');

        $this->assertEquals([$order->id], $byType->get('orders')->pluck('id')->all());
        $this->assertEquals([$product->id], $byType->get('products')->pluck('id')->all());
        $this->assertEquals([$customer->id], $byType->get('customers')->pluck('id')->all());
        $this->assertEquals(['ZEPHYR10'], $byType->get('coupons')->pluck('name')->all());
        $this->assertEquals(['Zephyr Supplies Ltd'], $byType->get('suppliers')->pluck('name')->all());
        $this->assertEquals(['PO-ZEPHYR-7'], $byType->get('purchase-orders')->pluck('name')->all());
        $this->assertEquals(['Zephyr Range'], $byType->get('categories')->pluck('name')->all());
        $this->assertEquals(['Zephyr courier fee'], $byType->get('expenses')->pluck('name')->all());

        $this->assertSame('/admin/orders', $byType->get('orders')->first()['link']['path']);
        $this->assertSame('/admin/coupons', $byType->get('coupons')->first()['link']['path']);
        $this->assertSame('/admin/accounting', $byType->get('expenses')->first()['link']['path']);
        $this->assertSame('/admin/purchase-orders', $byType->get('purchase-orders')->first()['link']['path']);
    }

    public function test_section_names_offer_navigation_even_when_no_records_match(): void
    {
        $this->actingAsAdmin();

        $response = $this->getJson('/api/global-search?q=coupons')->assertOk();

        $response->assertJsonPath('results', []);
        $this->assertSame(
            '/admin/coupons',
            collect($response->json('sections'))->firstWhere('title', 'Coupons')['path'],
        );

        $this->getJson('/api/global-search?q=settings')
            ->assertOk()
            ->assertJsonPath('sections.0.title', 'Settings');
    }

    public function test_a_term_that_matches_neither_records_nor_sections_returns_empty(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/global-search?q=zzzz-missing')
            ->assertOk()
            ->assertJson(['results' => [], 'sections' => []]);
    }

    public function test_blank_queries_short_circuit_without_touching_the_models(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/global-search?q=')
            ->assertOk()
            ->assertJson(['results' => [], 'sections' => []]);
    }

    public function test_like_wildcards_in_the_query_are_escaped_not_expanded(): void
    {
        $this->actingAsAdmin();
        Product::create(['name' => 'Percent Sign% Product', 'slug' => 'percent-sign', 'sku' => 'PCT-SIGN', 'price' => 1, 'is_active' => true]);

        $this->getJson('/api/global-search?q=%25')
            ->assertOk()
            ->assertJsonPath('results.0.name', 'Percent Sign% Product');
    }

    public function test_non_admins_cannot_use_global_search(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/global-search?q=anything')->assertForbidden();
    }
}
