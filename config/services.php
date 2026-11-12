<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | Stripe powers the storefront's "Credit / debit card" checkout via
    | Stripe Checkout (hosted redirect). While `dummy` is true no Stripe
    | API calls are made — the card flow runs locally and card orders are
    | recorded as paid. Set STRIPE_DUMMY=false with real test keys to go
    | through the actual Stripe sandbox, and live keys for production.
    |
    | key            = publishable key (pk_test_... / pk_live_...)
    | secret         = secret key (sk_test_... / sk_live_...) — never expose on the frontend
    | webhook_secret = signing secret for the checkout webhook (whsec_...)
    | dummy          = simulate the Stripe flow locally without calling the Stripe API
    |
    */

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'dummy' => filter_var(env('STRIPE_DUMMY', true), FILTER_VALIDATE_BOOLEAN),
    ],

];
