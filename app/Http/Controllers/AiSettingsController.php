<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Providers\AI\ChatClientFactory;
use App\Services\SiteSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class AiSettingsController extends Controller
{
    /** AI provider keys that may be configured from the admin panel. */
    protected array $publicKeys = [
        'ai_provider', 'ai_base_url', 'ai_model',
        'ai_temperature', 'ai_max_tokens', 'ai_timeout',
        'ai_enabled',
        'ai_default_analysis_period',
        'ai_recommendation_frequency',
        'ai_min_product_sales_threshold',
        'ai_low_stock_threshold',
        'ai_profit_margin_warning_threshold',
        'ai_sales_decline_warning_threshold',
        'ai_forecasting_period',
        'ai_response_language',
        'ai_daily_token_limit',
    ];

    protected array $secretKeys = ['ai_api_key'];

    protected array $intKeys = [
        'ai_max_tokens' => [1, 32168],
        'ai_timeout' => [1, 120],
        'ai_default_analysis_period' => [1, 365],
        'ai_recommendation_frequency' => [1, 720],
        'ai_min_product_sales_threshold' => [0, 10000000],
        'ai_low_stock_threshold' => [0, 100000],
        'ai_forecasting_period' => [1, 365],
        'ai_daily_token_limit' => [0, 100000000],
    ];

    protected array $floatKeys = [
        'ai_temperature' => [0, 2],
        'ai_profit_margin_warning_threshold' => [0, 100],
        'ai_sales_decline_warning_threshold' => [-100, 100],
    ];

    public function __construct(private readonly SiteSettings $siteSettings) {}

    public function index(): JsonResponse
    {
        $items = Setting::whereIn('key', $this->publicKeys)->where('is_secret', false)->get()->keyBy('key');
        $settings = [];
        foreach ($this->publicKeys as $key) {
            $raw = $items->get($key)?->value;
            if ($raw === null) {
                $settings[$key] = null;

                continue;
            }
            $settings[$key] = in_array($key, array_keys($this->intKeys), true) ? (int) $raw : (in_array($key, array_keys($this->floatKeys), true) ? (float) $raw : ($key === 'ai_enabled' ? filter_var($raw, FILTER_VALIDATE_BOOLEAN) : $raw));
        }

        // Secrets are never returned — only whether one is stored.
        $settings['ai_api_key_set'] = $this->siteSettings->secretValue('ai_api_key') !== null;

        // Sensible defaults for unset values — they are editable in the UI.
        return response()->json(array_merge([
            'ai_provider' => 'openai',
            'ai_base_url' => 'https://api.openai.com/v1',
            'ai_model' => 'gpt-4o-mini',
            'ai_temperature' => 0.2,
            'ai_max_tokens' => 2048,
            'ai_timeout' => 30,
            'ai_enabled' => true,
            'ai_default_analysis_period' => 30,
            'ai_recommendation_frequency' => 24,
            'ai_min_product_sales_threshold' => 5,
            'ai_low_stock_threshold' => 10,
            'ai_profit_margin_warning_threshold' => 15,
            'ai_sales_decline_warning_threshold' => -20,
            'ai_forecasting_period' => 30,
            'ai_response_language' => 'en',
            'ai_daily_token_limit' => 0,
        ], $settings));
    }

    public function store(Request $request): JsonResponse
    {
        $rules = array_fill_keys($this->publicKeys, ['sometimes', 'nullable']);
        foreach ($this->intKeys as $key => [$min, $max]) {
            $rules[$key] = ['sometimes', 'nullable', 'integer', "min:$min", "max:$max"];
        }
        foreach ($this->floatKeys as $key => [$min, $max]) {
            $rules[$key] = ['sometimes', 'nullable', 'numeric', "min:$min", "max:$max"];
        }
        $rules['ai_provider'] = ['sometimes', 'nullable', 'string', 'in:openai,openrouter,anthropic,google,ollama'];
        $rules['ai_base_url'] = ['sometimes', 'nullable', 'url', 'max:255'];
        $rules['ai_model'] = ['sometimes', 'nullable', 'string', 'max:255'];
        $rules['ai_enabled'] = ['sometimes', 'nullable', 'boolean'];
        $rules['ai_response_language'] = ['sometimes', 'nullable', 'string', 'max:10'];

        $data = $request->validate($rules + ['ai_api_key' => ['sometimes', 'nullable', 'string', 'max:512']]);

        // Prevent SSRF: an admin must not point the AI client at internal services.
        $provider = (string) ($data['ai_provider'] ?? $this->siteSettings->get('ai_provider') ?? 'openai');
        if (array_key_exists('ai_base_url', $data) && is_string($data['ai_base_url']) && trim($data['ai_base_url']) !== '') {
            $this->assertSafeBaseUrl(trim($data['ai_base_url']), $provider);
        }

        // Handle the secret API key separately — it is write-only and stored encrypted as a secret.
        if (array_key_exists('ai_api_key', $data) && is_string($data['ai_api_key']) && trim($data['ai_api_key']) !== '') {
            Setting::updateOrCreate(
                ['key' => 'ai_api_key'],
                ['value' => trim($data['ai_api_key']), 'type' => 'string', 'is_secret' => true]
            );
        }
        unset($data['ai_api_key']);

        foreach ($data as $key => $value) {
            if (in_array($key, $this->publicKeys, true) === false) {
                continue;
            }

            // An empty secret means "keep the stored one".
            if (in_array($key, $this->secretKeys, true) && ($value === null || $value === '')) {
                continue;
            }

            if ($key === 'ai_enabled') {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
            }

            Setting::updateOrCreate(
                ['key' => $key],
                [
                    'value' => is_array($value) ? json_encode($value) : (string) $value,
                    'type' => 'string',
                    'is_secret' => in_array($key, $this->secretKeys, true),
                ]
            );
        }

        Cache::forget(SiteSettings::SECRET_CACHE_KEY);
        Cache::forget(SiteSettings::CACHE_KEY);
        $this->siteSettings->forget();
        $this->siteSettings->applyRuntimeConfig();

        return response()->json(['message' => 'Saved']);
    }

    /** Lightweight connection test: asks the configured client to reply with one word. */
    public function testConnection(Request $request): JsonResponse
    {
        $key = $this->siteSettings->secretValue('ai_api_key');
        if (empty($key)) {
            return response()->json(['ok' => false, 'message' => 'No API key configured.'], 422);
        }

        $provider = (string) ($this->siteSettings->get('ai_provider') ?? 'openai');
        $baseUrl = (string) ($this->siteSettings->value('ai_base_url') ?? '');
        if ($baseUrl !== '') {
            try {
                $this->assertSafeBaseUrl($baseUrl, $provider);
            } catch (ValidationException $e) {
                return response()->json(['ok' => false, 'message' => 'Base URL host is not allowed.'], 422);
            }
        }

        $client = ChatClientFactory::make();
        $result = $client->chat(
            [['role' => 'user', 'content' => 'Reply with the single word: OK.']],
            [],
            null
        );

        if (isset($result['error'])) {
            return response()->json(['ok' => false, 'message' => $result['message'] ?? 'Connection failed.'], 422);
        }

        return response()->json(['ok' => true, 'message' => 'AI provider responded successfully.']);
    }

    /** Reject base URLs that point at internal/loopback hosts (SSRF guard). */
    private function assertSafeBaseUrl(string $url, string $provider): void
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            throw ValidationException::withMessages(['ai_base_url' => 'Invalid URL host.']);
        }

        $allowLocal = app()->environment('local') || $provider === 'ollama';
        $lower = strtolower($host);

        if ($allowLocal && in_array($lower, ['localhost', '127.0.0.1', '::1'], true)) {
            return;
        }

        if (in_array($lower, ['localhost', '127.0.0.1', '::1', '0.0.0.0'], true)) {
            throw ValidationException::withMessages(['ai_base_url' => 'Base URL host is not allowed.']);
        }

        $ip = filter_var($host, FILTER_VALIDATE_IP);
        if ($ip === false) {
            // Resolve hostname to catch DNS rebinding to private ranges.
            $resolved = gethostbyname($host);
            if ($resolved !== $host && filter_var($resolved, FILTER_VALIDATE_IP)) {
                $ip = $resolved;
            }
        }

        if ($ip !== false) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw ValidationException::withMessages(['ai_base_url' => 'Base URL host is not allowed.']);
            }

            return;
        }

        if (str_ends_with($lower, '.local') || str_ends_with($lower, '.internal') || str_ends_with($lower, '.localhost')) {
            throw ValidationException::withMessages(['ai_base_url' => 'Base URL host is not allowed.']);
        }
    }
}
