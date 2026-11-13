<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Supplier;
use App\Models\SupplierPurchaseOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExpenseManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_can_record_a_manual_expense(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->postJson('/api/expenses', [
            'name' => 'November rent',
            'category' => 'Rent',
            'date' => '2026-11-01',
            'amount' => 3500,
            'vat_status' => 'recoverable',
            'vat_amount' => 175,
            'payment_method' => 'bank_transfer',
        ])
            ->assertCreated()
            ->assertJsonPath('name', 'November rent')
            ->assertJsonPath('currency', 'AED');

        $this->assertDatabaseHas('expenses', ['name' => 'November rent', 'purchase_order_id' => null]);
    }

    public function test_expense_amount_must_be_positive(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->postJson('/api/expenses', [
            'name' => 'Zero expense',
            'category' => 'Other',
            'date' => '2026-11-01',
            'amount' => 0,
            'vat_status' => 'not_applicable',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount');
    }

    public function test_manual_expenses_can_be_updated_and_deleted(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $expense = Expense::create([
            'name' => 'Marketing',
            'category' => 'Marketing',
            'date' => '2026-11-01',
            'amount' => 500,
        ]);

        $this->putJson("/api/expenses/{$expense->id}", [
            'name' => 'Marketing (November)',
            'category' => 'Marketing',
            'date' => '2026-11-01',
            'amount' => 650,
            'vat_status' => 'not_applicable',
        ])->assertOk()->assertJsonPath('name', 'Marketing (November)');

        $this->deleteJson("/api/expenses/{$expense->id}")->assertOk();
        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_expenses_created_from_a_purchase_order_are_read_only(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $supplier = Supplier::create([
            'supplier_code' => 'SUP-0001',
            'legal_name' => 'Zephyr Supplies Ltd',
            'contact_person' => 'Amina Khan',
            'email' => 'amina@zephyr.test',
            'city' => 'Dubai',
            'address' => 'Warehouse 4',
        ]);

        $order = SupplierPurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'po_number' => 'PO-2026-0001',
            'total_amount' => 100,
            'status' => 'received',
        ]);

        $expense = Expense::create([
            'purchase_order_id' => $order->id,
            'name' => 'PO charges',
            'category' => 'Logistics',
            'date' => '2026-11-13',
            'amount' => 27.5,
        ]);

        $this->putJson("/api/expenses/{$expense->id}", [
            'name' => 'Changed',
            'category' => 'Logistics',
            'date' => '2026-11-13',
            'amount' => 1,
            'vat_status' => 'not_applicable',
        ])->assertStatus(409);

        $this->deleteJson("/api/expenses/{$expense->id}")->assertStatus(409);
        $this->assertDatabaseHas('expenses', ['id' => $expense->id, 'name' => 'PO charges']);
    }

    public function test_expense_writes_require_an_admin(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/expenses', [])->assertForbidden();
    }
}
