<?php

namespace App\Services\AI;

/**
 * Strips control characters and caps the length of free-form text before it is
 * embedded into an AI prompt. Database content (product names, categories) is
 * data, not instructions — this keeps a malicious name from breaking out of
 * the data context or blowing up the prompt.
 */
class TextSanitizer
{
    public static function clean(mixed $value, int $max = 200): mixed
    {
        if (is_string($value)) {
            // Drop control chars (except tab/newline) and NUL, then cap length.
            $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? $value;

            return mb_substr(trim($value), 0, $max);
        }

        if (is_array($value)) {
            $out = [];
            foreach ($value as $key => $item) {
                $out[$key] = self::clean($item, $max);
            }

            return $out;
        }

        return $value;
    }
}
