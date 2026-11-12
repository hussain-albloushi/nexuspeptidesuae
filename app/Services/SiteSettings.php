<?php

namespace App\Services;

use App\Models\Setting;
use DateTimeZone;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * System-level settings that must be applied before any application work
 * happens. The website timezone is authoritative: every timestamp written
 * by PHP (Eloquent timestamps included) uses it, and the database clock is
 * never consulted.
 */
class SiteSettings
{
    public const DEFAULT_TIMEZONE = 'Asia/Dubai';

    public const CACHE_KEY = 'site:settings';

    /** @return array<string, string|null> */
    public function all(): array
    {
        try {
            return Cache::rememberForever(self::CACHE_KEY, fn (): array => Setting::query()
                ->where('is_secret', false)
                ->pluck('value', 'key')
                ->all());
        } catch (Throwable) {
            // The settings table may not exist yet (fresh install, migration).
            return [];
        }
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $value = $this->all()[$key] ?? null;

        return $value === null || $value === '' ? $default : $value;
    }

    public function timezone(): string
    {
        $timezone = $this->get('timezone', self::DEFAULT_TIMEZONE);

        return in_array($timezone, DateTimeZone::listIdentifiers(), true)
            ? $timezone
            : self::DEFAULT_TIMEZONE;
    }

    /** Apply the website clock and sender identity to the current process. */
    public function applyRuntimeConfig(): void
    {
        $timezone = $this->timezone();
        config(['app.timezone' => $timezone]);
        date_default_timezone_set($timezone);

        $fromAddress = $this->get('mail_from_address');
        $fromName = $this->get('mail_from_name');

        if ($fromAddress !== null) {
            config(['mail.from.address' => $fromAddress]);
        }
        if ($fromName !== null) {
            config(['mail.from.name' => $fromName]);
        }
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
