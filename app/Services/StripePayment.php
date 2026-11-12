<?php

namespace App\Services;

use App\Models\Order;
use Stripe\Checkout\Session;
use Stripe\Coupon;
use Stripe\Exception\ExceptionInterface;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Stripe;
use Stripe\Webhook;

/**
 * Stripe Checkout integration for the storefront's "Credit / debit card"
 * payment option.
 *
 * While config('services.stripe.dummy') is true — the default — the card
 * flow runs entirely locally: no Stripe API calls are made and card orders
 * are recorded as paid immediately. Set STRIPE_DUMMY=false and provide real
 * test/live keys to route customers through Stripe Checkout for real.
 */
class StripePayment
{
    public function isDummy(): bool
    {
        return (bool) config('services.stripe.dummy', true);
    }

    /**
     * Create a Stripe Checkout Session (hosted redirect) for an order and
     * return the session URL the customer should be redirected to.
     *
     * @param  array{vatIncludedInPrice: bool, vatRate: float}  $taxContext  Whether order item prices already contain VAT and the VAT rate label.
     *
     * @throws ExceptionInterface
     */
    public function createCheckoutSession(Order $order, array $taxContext): string
    {
        Stripe::setApiKey((string) config('services.stripe.secret'));

        $currency = 'aed';
        $lineItems = [];

        foreach ($order->items as $item) {
            $lineItems[] = [
                'price_data' => [
                    'currency' => $currency,
                    'unit_amount' => (int) round((float) $item->price * 100),
                    'product_data' => ['name' => $item->name],
                ],
                'quantity' => (int) $item->quantity,
            ];
        }

        if ((float) $order->shipping_amount > 0) {
            $lineItems[] = [
                'price_data' => [
                    'currency' => $currency,
                    'unit_amount' => (int) round((float) $order->shipping_amount * 100),
                    'product_data' => ['name' => 'Shipping'],
                ],
                'quantity' => 1,
            ];
        }

        if (! ($taxContext['vatIncludedInPrice'] ?? true) && (float) $order->tax_amount > 0) {
            $lineItems[] = [
                'price_data' => [
                    'currency' => $currency,
                    'unit_amount' => (int) round((float) $order->tax_amount * 100),
                    'product_data' => ['name' => 'VAT ('.(float) ($taxContext['vatRate'] ?? 5).'%)'],
                ],
                'quantity' => 1,
            ];
        }

        $sessionParams = [
            'mode' => 'payment',
            'line_items' => $lineItems,
            'client_reference_id' => $order->order_number,
            'customer_email' => $order->user?->email,
            'metadata' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ],
            'success_url' => rtrim((string) config('app.url'), '/').'/my-orders?payment=success',
            'cancel_url' => rtrim((string) config('app.url'), '/').'/checkout?payment=cancelled',
        ];

        $discount = (float) $order->discount_amount;
        if ($discount > 0) {
            // Stripe Checkout cannot take a negative line item, so the order
            // discount is applied with a one-time coupon created on the fly.
            $coupon = Coupon::create([
                'name' => 'Order discount',
                'amount_off' => (int) round($discount * 100),
                'currency' => $currency,
                'duration' => 'once',
            ]);
            $sessionParams['discounts'] = [['coupon' => $coupon->id]];
        }

        return (string) Session::create($sessionParams)->url;
    }

    /**
     * Record an order as paid (idempotent) and note it in the status history.
     * Mirrors the admin "mark as paid" path; used for the dummy flow and for
     * confirmed Stripe webhook events.
     */
    public function markOrderPaid(Order $order, string $notes): void
    {
        if ($order->payment_status === 'paid') {
            return;
        }

        $order->update(['payment_status' => 'paid']);
        $order->statusHistory()->create([
            'old_status' => $order->status,
            'new_status' => $order->status,
            'notes' => $notes,
        ]);
    }

    /**
     * Verify and decode a Stripe webhook payload. Returns the event object.
     *
     * @throws SignatureVerificationException|\UnexpectedValueException
     */
    public function constructWebhookEvent(string $payload, string $signatureHeader): object
    {
        $secret = (string) config('services.stripe.webhook_secret');

        if ($secret === '') {
            throw new UnexpectedValueException('Stripe webhook secret is not configured.');
        }

        return Webhook::constructEvent($payload, $signatureHeader, $secret);
    }
}
