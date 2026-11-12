<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\SiteSettings;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    /** Keys that were part of the legacy free-form settings page. */
    protected array $publicKeys = [
        'store_name', 'store_logo', 'store_email', 'store_phone', 'store_address',
        'currency', 'timezone', 'tax_settings', 'social_media_links',
        'store_policies', 'seo_title', 'seo_description', 'email_settings', 'payment_methods',
        'mail_from_name', 'mail_from_address',
    ];

    public function __construct(private readonly SiteSettings $siteSettings) {}

    public function index(): JsonResponse
    {
        $items = Setting::whereIn('key', $this->publicKeys)->get();
        $settings = $items->mapWithKeys(fn (Setting $setting): array => [$setting->key => $setting->value])->all();
        $settings['timezone'] ??= $this->siteSettings->timezone();

        return response()->json($settings);
    }

    public function store(Request $request): JsonResponse
    {
        $rules = array_fill_keys($this->publicKeys, ['sometimes', 'nullable']);
        $rules['timezone'] = ['sometimes', 'required', 'string', Rule::in(DateTimeZone::listIdentifiers())];
        $rules['mail_from_name'] = ['sometimes', 'nullable', 'string', 'max:255'];
        $rules['mail_from_address'] = ['sometimes', 'nullable', 'email', 'max:255'];

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

            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => is_array($value) ? json_encode($value) : $value, 'type' => 'string', 'is_secret' => false]
            );
        }

        // The saved clock and sender identity take effect immediately.
        $this->siteSettings->forget();
        $this->siteSettings->applyRuntimeConfig();

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
