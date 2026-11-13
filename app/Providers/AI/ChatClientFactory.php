<?php

namespace App\Providers\AI;

use App\Models\Setting;
use App\Services\SiteSettings;

class ChatClientFactory
{
    public static function make(): ChatClient
    {
        $siteSettings = app(SiteSettings::class);

        $provider = self::settingValue('ai_provider') ?: 'openai';
        $key = $siteSettings->secretValue('ai_api_key') ?? (string) env('OPENAI_API_KEY');

        $model = self::settingValue('ai_model') ?: (string) env('OPENAI_MODEL', 'gpt-4o-mini');
        $baseUrl = self::settingValue('ai_base_url') ?: (string) env('OPENAI_BASE_URL', 'https://api.openai.com/v1');

        $temperature = (float) (self::settingValue('ai_temperature') ?? env('OPENAI_TEMPERATURE', 0.2));
        $maxTokens = (int) (self::settingValue('ai_max_tokens') ?? env('OPENAI_MAX_TOKENS', 2048));
        $timeout = (int) (self::settingValue('ai_timeout') ?? env('OPENAI_TIMEOUT', 30));

        return match ($provider) {
            default => new OpenAICompatibleChatClient($key, $baseUrl, $model, $temperature, $maxTokens, $timeout),
        };
    }

    private static function settingValue(string $key): ?string
    {
        try {
            $value = Setting::query()->where('key', $key)->value('value');

            return $value === null || $value === '' ? null : (string) $value;
        } catch (\Throwable) {
            return null;
        }
    }
}
