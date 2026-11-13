<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\SupplierPurchaseOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupplierManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_can_create_a_supplier(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $response = $this->postJson('/api/suppliers', [
            'legal_name' => 'Zephyr Supplies Ltd',
            'trading_name' => 'Zephyr',
            'contact_person' => 'Amina Khan',
            'email' => 'amina@zephyr.test',
            'city' => 'Dubai',
            'address' => 'Warehouse 4, Al Quoz',
        ])->assertCreated();

        $this->assertSame('Zephyr Supplies Ltd', $response->json('legal_name'));
        $this->assertSame('SUP-0001', $response->json('supplier_code'));
        $this->assertDatabaseHas('suppliers', ['email' => 'amina@zephyr.test', 'country' => 'UAE']);
    }

    public function test_supplier_payload_is_validated(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->postJson('/api/suppliers', [
            'trading_name' => 'No legal name',
            'contact_person' => 'Amina Khan',
            'email' => 'not-an-email',
            'city' => 'Dubai',
            'address' => 'Warehouse 4',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['legal_name', 'email']);
    }

    public function test_suppliers_can_be_updated(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $supplier = $this->supplier();

        $this->putJson("/api/suppliers/{$supplier->id}", [
            'legal_name' => 'Zephyr Supplies FZE',
            'contact_person' => 'Amina Khan',
            'email' => 'amina@zephyr.test',
            'city' => 'Sharjah',
            'address' => 'Warehouse 9',
            'status' => 'on_hold',
        ])
            ->assertOk()
            ->assertJsonPath('legal_name', 'Zephyr Supplies FZE')
            ->assertJsonPath('status', 'on_hold');
    }

    public function test_a_supplier_with_purchase_orders_cannot_be_deleted(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $supplier = $this->supplier();

        SupplierPurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'po_number' => 'PO-2026-0001',
            'total_amount' => 100,
            'status' => 'draft',
        ]);

        $this->deleteJson("/api/suppliers/{$supplier->id}")->assertStatus(409);
        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id]);
    }

    public function test_supplier_writes_require_an_admin(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/suppliers', [
            'legal_name' => 'Nope',
            'contact_person' => 'Nope',
            'email' => 'nope@example.test',
            'city' => 'Dubai',
            'address' => 'Nowhere',
        ])->assertForbidden();
    }

    private function supplier(): Supplier
    {
        return Supplier::create([
            'supplier_code' => 'SUP-0001',
            'legal_name' => 'Zephyr Supplies Ltd',
            'contact_person' => 'Amina Khan',
            'email' => 'amina@zephyr.test',
            'city' => 'Dubai',
            'address' => 'Warehouse 4',
        ]);
    }
}
