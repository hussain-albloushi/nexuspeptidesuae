<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\StripePayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives Stripe events for the storefront's card checkout.
 *
 * In dummy mode (default, STRIPE_DUMMY=true) the endpoint simply acknowledges
 * the call and does nothing — no Stripe-signed events exist yet. In live mode
 * the payload signature is verified against STRIPE_WEBHOOK_SECRET before any
 * state changes; unverifiable calls are rejected.
 */
class StripeWebhookController extends Controller
{
    public function __construct(private readonly StripePayment $stripe) {}

    public function handle(Request $request): JsonResponse
    {
        if ($this->stripe->isDummy()) {
            return response()->json(['received' => true, 'dummy' => true]);
        }

        try {
            $event = $this->stripe->constructWebhookEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature', '')
            );
        } catch (\Throwable) {
            return response()->json(['message' => 'Invalid webhook signature.'], 400);
        }

        if (($event->type ?? null) === 'checkout.session.completed') {
            $session = $event->data->object ?? null;
            $orderNumber = $session->client_reference_id ?? null;
            $order = is_string($orderNumber) ? Order::query()->where('order_number', $orderNumber)->first() : null;

            if ($order) {
                $this->stripe->markOrderPaid(
                    $order,
                    'Card payment confirmed by Stripe (checkout.session.completed'.(isset($session->id) ? ', session '.$session->id : '').').'
                );
            }
        }

        return response()->json(['received' => true]);
    }
}
