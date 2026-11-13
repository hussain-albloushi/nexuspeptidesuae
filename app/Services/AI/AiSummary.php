<?php

namespace App\Services\AI;

use App\Models\Setting;
use App\Providers\AI\ChatClientFactory;
use App\Services\SiteSettings;

/**
 * Ground-truth interpreter: builds a deterministic briefing from the analytics
 * services and, when an AI provider is configured, asks the LLM to phrase it in
 * natural language. Falls back to a templated summary whenever AI is disabled,
 * unconfigured, or errors — numbers always come from analytics, never the LLM.
 */
class AiSummary
{
    /** Whether AI interpretation is enabled and an API key is stored. */
    public static function isEnabled(): bool
    {
        try {
            $enabled = Setting::where('key', 'ai_enabled')->value('value');
            if ($enabled !== null && ! filter_var($enabled, FILTER_VALIDATE_BOOLEAN)) {
                return false;
            }

            return trim((string) (app(SiteSettings::class)->secretValue('ai_api_key') ?? env('OPENAI_API_KEY') ?? '')) !== '';
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Produce an AI-flavoured summary of the analytics payload. Returns the
     * summary text plus source ('ai'|'fallback') and generation time.
     */
    public static function summarize(array $analytics, string $instruction): array
    {
        $fallback = self::fallbackSummary($analytics);

        if (! self::isEnabled()) {
            return ['text' => $fallback, 'source' => 'fallback', 'generated_at' => now()->toIso8601String()];
        }

        try {
            $client = ChatClientFactory::make();
            $prompt = "You are a business intelligence analyst. Using ONLY the data below, write a concise summary (3-6 sentences). Do not invent numbers. Currency is AED.\n\nInstruction: {$instruction}\n\nData:\n".json_encode($analytics, JSON_PRETTY_PRINT);
            $result = $client->chat([
                ['role' => 'system', 'content' => 'You are a precise ecommerce business analyst. Use only the provided figures.'],
                ['role' => 'user', 'content' => $prompt],
            ], [], null);

            $text = trim((string) ($result['content'] ?? ''));

            if ($text !== '' && ! isset($result['error'])) {
                return ['text' => $text, 'source' => 'ai', 'generated_at' => now()->toIso8601String()];
            }
        } catch (\Throwable) {
            // fall through to templated summary
        }

        return ['text' => $fallback, 'source' => 'fallback', 'generated_at' => now()->toIso8601String()];
    }

    /** Deterministic templated summary computed from analytics figures. */
    private static function fallbackSummary(array $a): string
    {
        $parts = [];

        if (isset($a['revenue']['value'])) {
            $rev = number_format((float) $a['revenue']['value'], 2);
            $delta = $a['revenue']['delta_percent'] ?? null;
            $parts[] = "Revenue is AED {$rev}".($delta !== null ? sprintf(' (%+.1f%% vs the comparison period)', (float) $delta) : '');
        }
        if (isset($a['orders']['value'])) {
            $parts[] = 'Orders: '.(int) $a['orders']['value'];
        }
        if (isset($a['gross_profit']['value'])) {
            $parts[] = 'Gross profit: AED '.number_format((float) $a['gross_profit']['value'], 2);
        }
        if (isset($a['low_stock']) && (int) $a['low_stock'] > 0) {
            $parts[] = (int) $a['low_stock'].' products are running low on stock';
        }
        if (isset($a['refunds']) && (float) $a['refunds'] > 0) {
            $parts[] = 'Refunds total AED '.number_format((float) $a['refunds'], 2);
        }

        return $parts !== []
            ? implode('. ', $parts).'.'
            : 'Analytics computed successfully. Review the figures below for details.';
    }
}
