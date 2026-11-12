<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CouponController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            Coupon::orderBy('created_at', 'desc')->get()
        );
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(Coupon::findOrFail($id));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request->all());

        if (Coupon::where('code', $data['code'])->exists()) {
            throw ValidationException::withMessages([
                'code' => __('A coupon with this code already exists.'),
            ]);
        }

        $coupon = Coupon::create(array_merge($data, ['usage_count' => 0]));

        return response()->json($coupon, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $coupon = Coupon::findOrFail($id);
        $data = $this->validated($request->all());

        if ($coupon->code !== $data['code']
            && Coupon::where('code', $data['code'])->where('id', '!=', $id)->exists()) {
            throw ValidationException::withMessages([
                'code' => __('A coupon with this code already exists.'),
            ]);
        }

        $coupon->update($data);

        return response()->json($coupon);
    }

    public function destroy(int $id): JsonResponse
    {
        $coupon = Coupon::findOrFail($id);
        $coupon->delete();

        return response()->json(['deleted' => (int) $id]);
    }

    /**
     * Public (storefront) endpoint: validates a code against the current
     * order total and returns the applicable discount in the orders' currency.
     */
    public function validateCoupon(Request $request): JsonResponse
    {
        $input = $request->validate([
            'code' => ['required', 'string', 'max:60'],
            'orderTotal' => ['required', 'numeric', 'min:0'],
        ]);

        $orderTotal = (float) $input['orderTotal'];

        $coupon = Coupon::whereRaw('LOWER(code) = LOWER(?)', [trim($input['code'])])->first();
        if (! $coupon || ! $coupon->isValid()) {
            return response()->json($this->failure('This coupon code is not valid.'));
        }

        if ($coupon->min_order_amount && $orderTotal < (float) $coupon->min_order_amount) {
            return response()->json($this->failure(sprintf(
                'A minimum order of AED %s is required to use this coupon.',
                number_format((float) $coupon->min_order_amount, 2)
            )));
        }

        $discount = $coupon->discountFor($orderTotal);

        return response()->json([
            'ok' => true,
            'code' => $coupon->code,
            'type' => $coupon->type,
            'value' => (float) $coupon->value,
            'maxDiscount' => $coupon->max_discount !== null ? (float) $coupon->max_discount : null,
            'minOrderAmount' => $coupon->min_order_amount ? (float) $coupon->min_order_amount : null,
            'discount' => $discount,
        ]);
    }

    protected function failure(string $message): array
    {
        return [
            'ok' => false,
            'message' => $message,
            'code' => null,
            'type' => null,
            'value' => 0,
            'maxDiscount' => null,
            'minOrderAmount' => null,
            'discount' => 0,
        ];
    }

    protected function validated(array $input): array
    {
        $validated = Validator::make($input, [
            'code' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9][A-Za-z0-9_-]*$/'],
            'type' => ['required', 'in:percentage,fixed'],
            'value' => ['required', 'numeric', 'min:0', ($input['type'] ?? null) === 'percentage' ? 'max:100' : 'max:999999.99'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'max_discount' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', Rule::when(! empty($input['starts_at']), 'after_or_equal:starts_at')],
            'is_active' => ['boolean'],
        ])->validate();

        if (isset($validated['code'])) {
            $validated['code'] = strtoupper(trim($validated['code']));
        }

        $validated['value'] = (float) $validated['value'];
        if (isset($validated['min_order_amount'])) {
            $validated['min_order_amount'] = (float) $validated['min_order_amount'];
        }
        if (isset($validated['max_discount'])) {
            $validated['max_discount'] = (float) $validated['max_discount'];
        }

        return $validated;
    }
}
