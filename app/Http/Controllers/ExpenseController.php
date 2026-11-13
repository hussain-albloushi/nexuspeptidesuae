<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    public function index(Request $r)
    {
        return Expense::with('purchaseOrder:id,po_number')->orderByDesc('date')->get();
    }

    public function show($id)
    {
        return Expense::with('purchaseOrder:id,po_number')->findOrFail($id);
    }

    public function store(Request $request): JsonResponse
    {
        $expense = Expense::create($this->validated($request));

        return response()->json($expense, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $expense = Expense::findOrFail($id);

        if ($expense->purchase_order_id !== null) {
            return response()->json([
                'message' => 'This expense was created from a purchase order and cannot be edited here.',
            ], 409);
        }

        $expense->update($this->validated($request));

        return response()->json($expense);
    }

    public function destroy(int $id): JsonResponse
    {
        $expense = Expense::findOrFail($id);

        if ($expense->purchase_order_id !== null) {
            return response()->json([
                'message' => 'This expense was created from a purchase order and cannot be deleted here.',
            ], 409);
        }

        $expense->delete();

        return response()->json(['deleted' => (int) $id]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999.99'],
            'currency' => ['nullable', 'string', 'size:3'],
            'vat_inclusive' => ['boolean'],
            'vat_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'vat_status' => ['required', Rule::in(['recoverable', 'non_recoverable', 'not_applicable'])],
            'supplier_payee' => ['nullable', 'string', 'max:255'],
            'payment_method' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $data['currency'] = $data['currency'] ?? 'AED';
        $data['vat_amount'] = (float) ($data['vat_amount'] ?? 0);

        return $data;
    }
}
