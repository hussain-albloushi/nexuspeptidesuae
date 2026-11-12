<?php

namespace App\Http\Controllers;

use App\Models\Content;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Services\StripePayment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Stripe\Exception\ExceptionInterface;

class OrderController extends Controller
{
    public function __construct(private readonly StripePayment $stripe) {}

    public function index(Request $r): Collection
    {
        $data = $r->validate(['q' => ['nullable', 'string', 'max:255']]);
        $term = ltrim(trim($data['q'] ?? ''), '#');
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($term)).'%';

        return Order::with('user')
            ->when($term !== '', function (Builder $query) use ($pattern): void {
                $query->where(function (Builder $query) use ($pattern): void {
                    $query->whereRaw("LOWER(order_number) LIKE ? ESCAPE '!'", [$pattern])
                        ->orWhereRaw("id LIKE ? ESCAPE '!'", [$pattern])
                        ->orWhereHas('user', function (Builder $query) use ($pattern): void {
                            $query->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$pattern])
                                ->orWhereRaw("LOWER(email) LIKE ? ESCAPE '!'", [$pattern]);
                        });
                });
            })
            ->latest()->limit(200)->get();
    }

    public function show($id)
    {
        return Order::with(['user', 'items', 'statusHistory'])->findOrFail($id);
    }

    public function myOrders(Request $r)
    {
        $userId = $r->user()->id;

        return Order::with(['items', 'statusHistory', 'items.product', 'returnRequests'])
            ->where('user_id', $userId)
            ->latest()
            ->get();
    }

    /**
     * Customer asks to return a delivered order. Allowed once, inside the
     * configured return window (Website Content → Store → "Return window"),
     * and only while the order is delivered and not already refunded.
     */
    public function requestReturn(Request $r, int $id): JsonResponse
    {
        $data = $r->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $order = Order::with(['returnRequests', 'statusHistory' => fn ($history) => $history
            ->where('new_status', 'delivered')->orderByDesc('created_at')])
            ->where('user_id', $r->user()->id)
            ->findOrFail($id);

        if ($order->status !== 'delivered') {
            throw ValidationException::withMessages([
                'order' => 'Only delivered orders can be returned.',
            ]);
        }

        if (in_array($order->payment_status, ['refunded', 'partially_refunded'], true)) {
            throw ValidationException::withMessages([
                'order' => 'This order has already been refunded.',
            ]);
        }

        if ($order->returnRequests->where('status', '!=', 'rejected')->isNotEmpty()) {
            throw ValidationException::withMessages([
                'order' => 'A return request already exists for this order.',
            ]);
        }

        $deliveredAt = $order->statusHistory->first()?->created_at ?? $order->updated_at;
        $window = $this->returnWindowDays();
        if ($window <= 0 || $deliveredAt->copy()->addDays($window)->isPast()) {
            throw ValidationException::withMessages([
                'order' => $window <= 0
                    ? 'Returns are currently disabled.'
                    : 'The return window of '.$window.' days after delivery has passed.',
            ]);
        }

        $request = ReturnRequest::create([
            'order_id' => $order->id,
            'user_id' => $r->user()->id,
            'reason' => $data['reason'],
            'status' => ReturnRequest::STATUS_REQUESTED,
        ]);

        $order->statusHistory()->create([
            'old_status' => $order->status,
            'new_status' => $order->status,
            'notes' => 'Return requested by the customer. Our team will review it shortly.',
        ]);

        return response()->json(['message' => 'Return request received. We will review it and contact you.', 'return_request' => $request], 201);
    }

    /** Configured return window in days (Website Content → Store), default 14, 0 disables returns. */
    private function returnWindowDays(): int
    {
        $settings = $this->storeSettings();
        $days = (int) round((float) ($settings['returnWindowDays'] ?? 14));

        return max(0, $days);
    }

    /**
     * Storefront checkout. Prices and totals are recomputed server-side from
     * the products table — the client only sends slugs and quantities.
     */
    public function store(Request $r): JsonResponse
    {
        $data = $r->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.slug' => ['required', 'string', 'max:255'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:1000'],
            'shipping_address' => ['required', 'array'],
            'shipping_address.*' => ['nullable', 'string', 'max:255'],
            'shipping_address.name' => ['required', 'string', 'max:255'],
            'shipping_address.email' => ['required', 'email', 'max:255'],
            'shipping_address.phone' => ['required', 'string', 'max:255'],
            'shipping_address.address' => ['required', 'string', 'max:255'],
            'shipping_address.city' => ['required', 'string', 'max:255'],
            'shipping_address.country' => ['required', 'string', 'max:255'],
            'payment_method' => ['required', 'in:cod,stripe'],
            'coupon_code' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $slugs = collect($data['items'])->pluck('slug')->unique()->values();
        $products = Product::whereIn('slug', $slugs)->where('is_active', true)->get()->keyBy('slug');

        $missing = $slugs->diff($products->keys());
        if ($missing->isNotEmpty()) {
            return response()->json([
                'message' => 'Some items in your basket are no longer available.',
                'items' => $missing->values(),
            ], 422);
        }

        // Server-side totals — never trust client-supplied amounts.
        $subtotal = 0;
        foreach ($data['items'] as $line) {
            $subtotal += (float) $products[$line['slug']]->price * (int) $line['qty'];
        }
        $subtotal = round($subtotal, 2);

        $settings = $this->storeSettings();
        $shipping = $this->shippingFor($subtotal, $settings);
        $tax = $this->taxFor($subtotal, $settings);

        $userId = $r->user()->id;
        $codFee = $this->codFeeFor($data['payment_method'], $settings);
        $checkoutUrl = null;

        try {
            $order = DB::transaction(function () use ($userId, $data, $products, $subtotal, $shipping, $tax, $settings, $codFee, &$checkoutUrl) {
                $discount = $this->couponDiscount($data['coupon_code'] ?? null, $subtotal);
                $addedTax = $settings['vatIncludedInPrice'] ? 0 : $tax;
                $total = round(max(0, $subtotal - $discount) + $shipping + $addedTax + $codFee, 2);

                $order = Order::create([
                    'user_id' => $userId,
                    'order_number' => $this->generateOrderNumber(),
                    'status' => 'pending',
                    'subtotal' => $subtotal,
                    'tax_amount' => $tax,
                    'discount_amount' => $discount,
                    'shipping_amount' => $shipping,
                    'cod_fee' => $codFee,
                    'total_amount' => $total,
                    'payment_method' => $data['payment_method'],
                    'payment_status' => 'pending',
                    'shipping_status' => 'pending',
                    'shipping_address' => json_encode($data['shipping_address']),
                    'billing_address' => json_encode([
                        'name' => $data['shipping_address']['name'] ?? null,
                        'email' => $data['shipping_address']['email'] ?? null,
                    ]),
                    'notes' => $data['notes'] ?? null,
                ]);

                foreach ($data['items'] as $line) {
                    $product = $products[$line['slug']];
                    $qty = (int) $line['qty'];

                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'name' => $product->name,
                        'price' => $product->price,
                        'quantity' => $qty,
                        'total_price' => round((float) $product->price * $qty, 2),
                    ]);

                    $product->adjustStock(-$qty, 'sale', 'Order '.$order->order_number);
                }

                if ($data['payment_method'] === 'stripe') {
                    if ($this->stripe->isDummy()) {
                        // Dummy mode: no Stripe API call — the card flow runs
                        // locally and the order is recorded as paid.
                        $this->stripe->markOrderPaid(
                            $order,
                            'Simulated card payment (Stripe dummy mode). No payment was collected and no Stripe API call was made.'
                        );
                    } else {
                        $checkoutUrl = $this->stripe->createCheckoutSession($order->load('items'), [
                            'vatIncludedInPrice' => $settings['vatIncludedInPrice'],
                            'vatRate' => $settings['vatRate'],
                        ]);
                    }
                }

                return $order;
            });
        } catch (ExceptionInterface $e) {
            // The order (and stock deduction) rolled back with the transaction.
            report($e);

            return response()->json(['message' => 'Card payment could not be initialised. Please try again or choose cash on delivery.'], 502);
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'Insufficient stock')) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
            throw $e;
        }

        return response()->json(array_merge($order->load('items')->toArray(), [
            'checkout_url' => $checkoutUrl,
            'dummy_payment' => $data['payment_method'] === 'stripe' && $this->stripe->isDummy(),
        ]), 201);
    }

    protected function generateOrderNumber(): string
    {
        do {
            $number = 'NP-'.Str::upper(Str::random(8));
        } while (Order::where('order_number', $number)->exists());

        return $number;
    }

    protected function couponDiscount(?string $code, float $subtotal): float
    {
        if (! $code) {
            return 0;
        }

        $coupon = Coupon::whereRaw('LOWER(code) = LOWER(?)', [trim($code)])->lockForUpdate()->first();

        if (! $coupon || ! $coupon->isValid() || $subtotal < (float) $coupon->min_order_amount) {
            throw ValidationException::withMessages([
                'coupon_code' => 'This coupon is no longer available for this order.',
            ]);
        }

        $discount = $coupon->discountFor($subtotal);
        $coupon->increment('usage_count');

        return $discount;
    }

    /** Published storefront settings, falling back to the frontend defaults. */
    protected function storeSettings(): array
    {
        $defaults = [
            'vatEnabled' => true,
            'vatRate' => 5,
            'vatIncludedInPrice' => false,
            ...config('storefront-content.shipping_defaults'),
        ];

        try {
            $content = Content::where('key', 'store')->where('type', 'group')->where('is_active', true)->first();
            $stored = $content && is_array($content->data) ? $content->data : [];
        } catch (\Throwable $e) {
            $stored = [];
        }

        $settings = array_merge($defaults, array_intersect_key($stored, $defaults));
        $settings['flatRate'] = $stored['flatRate'] ?? $stored['shippingOptions'][0]['cost'] ?? $defaults['flatRate'];

        // Admin <select> controls persist booleans as 'true'/'false' strings.
        foreach (['vatEnabled', 'vatIncludedInPrice'] as $key) {
            $settings[$key] = filter_var($settings[$key], FILTER_VALIDATE_BOOLEAN);
        }
        $settings['vatRate'] = (float) $settings['vatRate'];
        $settings['flatRate'] = (float) $settings['flatRate'];
        $settings['codFee'] = max(0, (float) ($settings['codFee'] ?? 0));

        return $settings;
    }

    /** Cash-on-delivery handling fee, charged only for COD orders. */
    protected function codFeeFor(?string $paymentMethod, array $settings): float
    {
        return $paymentMethod === 'cod' ? round($settings['codFee'], 2) : 0.0;
    }

    protected function shippingFor(float $subtotal, array $settings): float
    {
        $mode = $settings['shippingMode'];

        if ($mode === 'free') {
            return 0;
        }

        if ($mode === 'flat') {
            $threshold = $settings['freeShippingThreshold'];
            if ($threshold !== null && $subtotal >= (float) $threshold) {
                return 0;
            }

            return round($settings['flatRate'], 2);
        }

        // 'custom' / 'to be confirmed' — no shipping charged yet.
        return 0;
    }

    protected function taxFor(float $subtotal, array $settings): float
    {
        if (! $settings['vatEnabled']) {
            return 0;
        }

        $rate = $settings['vatRate'];
        $tax = $settings['vatIncludedInPrice']
            ? $subtotal * ($rate / (100 + $rate))
            : $subtotal * ($rate / 100);

        return round($tax, 2);
    }
}
