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

    /*
    |--------------------------------------------------------------------------
    | Google reCAPTCHA (Help Center contact form)
    |--------------------------------------------------------------------------
    |
    | Protects the public /api/contact endpoint from bot spam. Keys created
    | at https://www.google.com/recaptcha/admin work as env fallbacks for
    | local development; the admin Settings page overrides them with rows
    | in the settings table (recaptcha_site_key / recaptcha_secret_key).
    |
    | key       = site key (RECAPTCHA_SITE_KEY) — public, embedded in the page
    | secret    = secret key (RECAPTCHA_SECRET_KEY) — server side only
    | threshold = minimum v3 score a submission must reach (v2 has no score)
    |
    */

    'recaptcha' => [
        'key' => env('RECAPTCHA_SITE_KEY'),
        'secret' => env('RECAPTCHA_SECRET_KEY'),
        'threshold' => env('RECAPTCHA_THRESHOLD', 0.5),
        'timeout' => env('RECAPTCHA_TIMEOUT', 10),
    ],

];
