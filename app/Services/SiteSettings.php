<?php

namespace App\Services;

use App\Models\Setting;
use DateTimeZone;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
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

    public const SECRET_CACHE_KEY = 'site:settings:secret';

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

    /**
     * Read a single secret setting. Kept in its own cache store so secrets are
     * never mixed into the public all() bag or returned by any settings API.
     */
    public function secretValue(string $key): ?string
    {
        try {
            $secrets = Cache::rememberForever(self::SECRET_CACHE_KEY, fn (): array => Setting::query()
                ->where('is_secret', true)
                ->pluck('value', 'key')
                ->all());
        } catch (Throwable) {
            return null;
        }

        $value = $secrets[$key] ?? null;
        if ($value === null || $value === '') {
            return null;
        }

        // Secrets are encrypted at rest; legacy rows may still be plaintext.
        try {
            return Crypt::decryptString($value);
        } catch (Throwable) {
            return $value;
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

        $this->applyMailTransport();
    }

    /**
     * Override the SMTP transport from admin-stored settings, but only for the
     * fields the admin actually filled in — anything left blank keeps the
     * .env / config value, so a half-configured panel never silently disables
     * mail. The password is read from the secret cache and never leaves it.
     */
    public function applyMailTransport(): void
    {
        $overrides = [];

        foreach (['host' => 'mail_host', 'username' => 'mail_username'] as $configKey => $settingKey) {
            $value = $this->get($settingKey);
            if ($value !== null) {
                $overrides[$configKey] = $value;
            }
        }

        $port = $this->get('mail_port');
        if ($port !== null && ctype_digit($port)) {
            $overrides['port'] = (int) $port;
        }

        $scheme = $this->get('mail_scheme');
        if ($scheme !== null && in_array($scheme, ['tls', 'ssl'], true)) {
            $overrides['scheme'] = $scheme;
        }

        $password = $this->secretValue('mail_password');
        if ($password !== null) {
            $overrides['password'] = $password;
        }

        if ($overrides !== []) {
            config(['mail.mailers.smtp' => array_merge(config('mail.mailers.smtp', []), $overrides)]);
        }

        $transport = $this->get('mail_transport');
        if ($transport !== null && array_key_exists($transport, config('mail.mailers', []))) {
            config(['mail.default' => $transport]);
        }
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::SECRET_CACHE_KEY);
    }
}
