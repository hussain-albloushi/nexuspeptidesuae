<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\SiteSettings;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    /** Keys that were part of the legacy free-form settings page. */
    protected array $publicKeys = [
        'store_name', 'store_logo', 'store_email', 'store_phone', 'store_address',
        'currency', 'timezone', 'tax_settings', 'social_media_links',
        'store_policies', 'seo_title', 'seo_description', 'email_settings', 'payment_methods',
        'mail_from_name', 'mail_from_address', 'contact_delivery_email',
        'mail_transport', 'mail_host', 'mail_port', 'mail_scheme', 'mail_username', 'mail_password',
        'recaptcha_enabled', 'recaptcha_site_key', 'recaptcha_secret_key',
    ];

    /** Stored with is_secret so they never enter the cached settings bag or an API response. */
    protected array $secretKeys = ['recaptcha_secret_key', 'mail_password'];

    public function __construct(private readonly SiteSettings $siteSettings) {}

    public function index(): JsonResponse
    {
        $items = Setting::whereIn('key', $this->publicKeys)->where('is_secret', false)->get();
        $settings = $items->mapWithKeys(fn (Setting $setting): array => [$setting->key => $setting->value])->all();
        $settings['timezone'] ??= $this->siteSettings->timezone();

        // Secrets never leave the server; the admin UI only needs to know one is stored.
        $settings['recaptcha_secret_key_set'] = $this->siteSettings->secretValue('recaptcha_secret_key') !== null;
        $settings['mail_password_set'] = $this->siteSettings->secretValue('mail_password') !== null;
        unset($settings['recaptcha_secret_key'], $settings['mail_password']);

        return response()->json($settings);
    }

    public function store(Request $request): JsonResponse
    {
        $rules = array_fill_keys($this->publicKeys, ['sometimes', 'nullable']);
        $rules['timezone'] = ['sometimes', 'required', 'string', Rule::in(DateTimeZone::listIdentifiers())];
        $rules['mail_from_name'] = ['sometimes', 'nullable', 'string', 'max:255'];
        $rules['mail_from_address'] = ['sometimes', 'nullable', 'email', 'max:255'];
        $rules['contact_delivery_email'] = ['sometimes', 'nullable', 'email', 'max:255'];
        $rules['recaptcha_enabled'] = ['sometimes', 'nullable', 'boolean'];
        $rules['recaptcha_site_key'] = ['sometimes', 'nullable', 'string', 'max:255'];
        $rules['recaptcha_secret_key'] = ['sometimes', 'nullable', 'string', 'max:255'];
        $rules['mail_transport'] = ['sometimes', 'nullable', Rule::in(['', 'smtp', 'log', 'array', 'sendmail'])];
        $rules['mail_host'] = ['sometimes', 'nullable', 'string', 'max:255'];
        $rules['mail_port'] = ['sometimes', 'nullable', 'integer', 'between:1,65535'];
        $rules['mail_scheme'] = ['sometimes', 'nullable', Rule::in(['', 'tls', 'ssl'])];
        $rules['mail_username'] = ['sometimes', 'nullable', 'string', 'max:255'];
        $rules['mail_password'] = ['sometimes', 'nullable', 'string', 'max:255'];

        $data = $request->validate($rules);

        // A timezone change governs the writes made by this very request.
        if (isset($data['timezone']) && is_string($data['timezone'])) {
            config(['app.timezone' => $data['timezone']]);
            date_default_timezone_set($data['timezone']);
        }

        foreach ($data as $key => $value) {
            if (! in_array($key, $this->publicKeys, true)) {
                continue;
            }

            // An empty secret means "keep the stored one" — the UI never re-sends it.
            if (in_array($key, $this->secretKeys, true) && ($value === null || $value === '')) {
                continue;
            }

            if ($key === 'recaptcha_enabled') {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
            }

            Setting::updateOrCreate(
                ['key' => $key],
                [
                    'value' => is_array($value) ? json_encode($value) : $value,
                    'type' => 'string',
                    'is_secret' => in_array($key, $this->secretKeys, true),
                ]
            );
        }

        // The saved clock and sender identity take effect immediately.
        $this->siteSettings->forget();
        $this->siteSettings->applyRuntimeConfig();
        Mail::forgetMailers();

        return response()->json([
            'message' => 'Saved',
            'timezone' => $this->siteSettings->timezone(),
        ]);
    }

    public function show(string $key): JsonResponse
    {
        if (! in_array($key, $this->publicKeys, true)) {
            return response()->json(['message' => 'Not found or secret'], 404);
        }

        return response()->json(Setting::where('key', $key)->firstOrFail());
    }
}
