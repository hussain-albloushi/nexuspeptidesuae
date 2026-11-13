<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SupplierController extends Controller
{
    public function index(Request $r)
    {
        return Supplier::withCount('products')->latest()->get();
    }

    public function show($id)
    {
        return Supplier::with(['products', 'purchaseOrders'])->findOrFail($id);
    }

    public function store(Request $request): JsonResponse
    {
        $supplier = Supplier::create([
            // Temporary unique value; replaced with SUP-0001 below for a stable, readable code.
            'supplier_code' => 'tmp-'.Str::uuid(),
            ...$this->validated($request),
        ]);

        $supplier->update([
            'supplier_code' => 'SUP-'.str_pad((string) $supplier->id, 4, '0', STR_PAD_LEFT),
        ]);

        return response()->json($supplier, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $supplier = Supplier::findOrFail($id);
        $supplier->update($this->validated($request, $id));

        return response()->json($supplier);
    }

    public function destroy(int $id): JsonResponse
    {
        $supplier = Supplier::findOrFail($id);

        if ($supplier->purchaseOrders()->exists()) {
            return response()->json([
                'message' => 'This supplier has purchase orders and cannot be deleted.',
            ], 409);
        }

        $supplier->delete();

        return response()->json(['deleted' => (int) $id]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?int $id = null): array
    {
        $data = $request->validate([
            'legal_name' => ['required', 'string', 'max:255'],
            'trading_name' => ['nullable', 'string', 'max:255'],
            'contact_person' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('suppliers', 'email')->ignore($id)],
            'phone' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:50'],
            'country' => ['nullable', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:500'],
            'tax_reg_number' => ['nullable', 'string', 'max:100'],
            'payment_terms' => ['nullable', 'string', 'max:100'],
            'default_currency' => ['nullable', 'string', 'size:3'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'on_hold', 'pending_review'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        // These columns are NOT NULL with defaults; a null from the form would fail the insert.
        $data['country'] = $data['country'] ?? 'UAE';
        $data['default_currency'] = $data['default_currency'] ?? 'AED';
        $data['status'] = $data['status'] ?? 'active';

        return $data;
    }
}
