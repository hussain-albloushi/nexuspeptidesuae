<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Google reCAPTCHA (v2 checkbox or v3 invisible) verification.
 *
 * Keys are managed on the admin Settings page (recaptcha_site_key,
 * recaptcha_secret_key) with env fallbacks (RECAPTCHA_SITE_KEY,
 * RECAPTCHA_SECRET_KEY) so local development works before the database
 * rows exist. The secret key is stored with is_secret = true, keeping it
 * out of the cached SiteSettings bag entirely.
 */
class GoogleRecaptcha
{
    public const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    public function __construct(private readonly SiteSettings $siteSettings) {}

    public function siteKey(): ?string
    {
        return $this->siteSettings->get('recaptcha_site_key', config('services.recaptcha.key'));
    }

    public function secretKey(): ?string
    {
        $stored = $this->siteSettings->secretValue('recaptcha_secret_key');

        return filled($stored) ? $stored : config('services.recaptcha.secret');
    }

    /**
     * True once both keys are known and the admin has not switched the
     * toggle off. When false the form works without any token.
     */
    public function enabled(): bool
    {
        if ($this->siteSettings->get('recaptcha_enabled') === '0') {
            return false;
        }

        return $this->siteKey() !== null && $this->secretKey() !== null;
    }

    /** The site key is public; only hand it to the frontend when it matters. */
    public function publicSiteKey(): ?string
    {
        return $this->enabled() ? $this->siteKey() : null;
    }

    /**
     * Exchange the browser token with Google. v3 responses carry a score
     * that must clear the configured threshold; v2 checkbox responses have
     * no score, so verifying `success` (and the action, when returned) is
     * enough. Any transport error is treated as a failed verification.
     */
    public function verify(?string $token, string $expectedAction): bool
    {
        if (blank($token)) {
            return false;
        }

        try {
            $payload = Http::asForm()
                ->timeout((float) config('services.recaptcha.timeout', 10))
                ->post(self::VERIFY_URL, [
                    'secret' => (string) $this->secretKey(),
                    'response' => $token,
                    'remoteip' => request()->ip(),
                ])
                ->json();
        } catch (Throwable) {
            return false;
        }

        if (! is_array($payload) || ($payload['success'] ?? false) !== true) {
            return false;
        }

        if (isset($payload['action']) && $payload['action'] !== $expectedAction) {
            return false;
        }

        if (isset($payload['score']) && $payload['score'] < (float) config('services.recaptcha.threshold', 0.5)) {
            return false;
        }

        return true;
    }
}
