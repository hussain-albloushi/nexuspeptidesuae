<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierPurchaseOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_order_totals_are_computed_server_side(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $supplier = $this->supplier();
        $first = $this->product('First product', 0);
        $second = $this->product('Second product', 100);

        $response = $this->postJson('/api/purchase-orders', [
            'supplier_id' => $supplier->id,
            'po_date' => '2026-11-13',
            'shipping_cost' => 20,
            'customs_cost' => 5,
            'additional_charges' => 2.5,
            // A client-supplied total must never be trusted.
            'total_amount' => 1,
            'items' => [
                ['product_id' => $first->id, 'quantity' => 2, 'unit_cost' => 100, 'discount' => 10, 'tax' => 5],
                ['product_id' => $second->id, 'quantity' => 1, 'unit_cost' => 50],
            ],
        ])->assertCreated();

        // 2×100 − 10 + 5 = 195; 1×50 = 50; charges 27.50 → 272.50
        $this->assertEquals(272.5, (float) $response->json('total_amount'));
        $this->assertStringStartsWith('PO-', (string) $response->json('po_number'));
        $this->assertSame('draft', $response->json('status'));
        $this->assertCount(2, $response->json('items'));
        $this->assertEquals(195, (float) $response->json('items')[0]['line_total']);
    }

    public function test_receiving_moves_stock_and_records_charges_as_an_expense(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $supplier = $this->supplier();
        $outOfStock = $this->product('Out of stock', 0);
        $inStock = $this->product('In stock', 100);

        $orderId = $this->createOrder($supplier, $outOfStock, $inStock)->json('id');

        $this->postJson("/api/purchase-orders/{$orderId}/receive")->assertOk();

        $this->assertSame(2, $outOfStock->fresh()->stock_quantity);
        $this->assertSame('low_stock', $outOfStock->fresh()->stock_status);
        $this->assertSame(101, $inStock->fresh()->stock_quantity);
        $this->assertSame(2, InventoryTransaction::count());

        $expense = Expense::sole();
        $this->assertEquals(27.5, (float) $expense->amount);
        $this->assertSame('Logistics', $expense->category);
        $this->assertSame($orderId, $expense->purchase_order_id);
        $this->assertSame('Zephyr', $expense->supplier_payee);

        $this->assertSame('received', SupplierPurchaseOrder::findOrFail($orderId)->status);
    }

    public function test_receiving_twice_is_rejected_and_does_not_duplicate_the_expense(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $supplier = $this->supplier();
        $first = $this->product('First product', 5);
        $second = $this->product('Second product', 5);

        $orderId = $this->createOrder($supplier, $first, $second)->json('id');
        $this->postJson("/api/purchase-orders/{$orderId}/receive")->assertOk();

        $this->postJson("/api/purchase-orders/{$orderId}/receive")->assertStatus(409);

        $this->assertSame(1, Expense::count());
        $this->assertSame(7, $first->fresh()->stock_quantity);
    }

    public function test_a_purchase_order_without_charges_creates_no_expense(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $supplier = $this->supplier();
        $first = $this->product('First product', 5);
        $second = $this->product('Second product', 5);

        $orderId = $this->createOrder($supplier, $first, $second, [
            'shipping_cost' => 0,
            'customs_cost' => 0,
            'additional_charges' => 0,
        ])->json('id');

        $this->postJson("/api/purchase-orders/{$orderId}/receive")->assertOk();

        $this->assertSame(0, Expense::count());
    }

    public function test_a_received_order_cannot_be_edited_or_deleted(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $supplier = $this->supplier();
        $first = $this->product('First product', 5);
        $second = $this->product('Second product', 5);

        $orderId = $this->createOrder($supplier, $first, $second)->json('id');
        $this->postJson("/api/purchase-orders/{$orderId}/receive")->assertOk();

        $this->putJson("/api/purchase-orders/{$orderId}", [
            'supplier_id' => $supplier->id,
            'po_date' => '2026-11-13',
            'items' => [['product_id' => $first->id, 'quantity' => 1, 'unit_cost' => 10]],
        ])->assertStatus(409);

        $this->deleteJson("/api/purchase-orders/{$orderId}")->assertStatus(409);
    }

    public function test_purchase_order_writes_require_an_admin(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/purchase-orders', [])->assertForbidden();
    }

    private function supplier(): Supplier
    {
        return Supplier::create([
            'supplier_code' => 'SUP-0001',
            'legal_name' => 'Zephyr Supplies Ltd',
            'trading_name' => 'Zephyr',
            'contact_person' => 'Amina Khan',
            'email' => 'amina@zephyr.test',
            'city' => 'Dubai',
            'address' => 'Warehouse 4',
        ]);
    }

    private function product(string $name, int $stock): Product
    {
        return Product::create([
            'name' => $name,
            'slug' => str($name)->slug(),
            'sku' => 'SKU-'.str($name)->slug(),
            'price' => 150,
            'cost_price' => 50,
            'stock_quantity' => $stock,
            'low_stock_threshold' => 5,
            'is_active' => true,
        ]);
    }

    private function createOrder(Supplier $supplier, Product $first, Product $second, array $overrides = [])
    {
        return $this->postJson('/api/purchase-orders', array_merge([
            'supplier_id' => $supplier->id,
            'po_date' => '2026-11-13',
            'shipping_cost' => 20,
            'customs_cost' => 5,
            'additional_charges' => 2.5,
            'items' => [
                ['product_id' => $first->id, 'quantity' => 2, 'unit_cost' => 100],
                ['product_id' => $second->id, 'quantity' => 1, 'unit_cost' => 50],
            ],
        ], $overrides))->assertCreated();
    }
}
