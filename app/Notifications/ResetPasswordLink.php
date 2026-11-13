<?php

namespace App\Notifications;

use App\Notifications\Concerns\RendersEmailTemplate;
use Illuminate\Notifications\Notification;

class ResetPasswordLink extends Notification
{
    use RendersEmailTemplate;

    public const TEMPLATE_KEY = 'password_reset';

    public const EXPIRY_MINUTES = 60;

    public function __construct(public string $token, public string $email) {}

    /**
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    public function parts(string $name = 'there'): array
    {
        // The reset token is embedded here and is NEVER editable by admins: the
        // template can place {reset_url} anywhere in the body, but cannot forge it.
        $resetUrl = rtrim((string) config('app.url'), '/').'/reset-password?token='.urlencode($this->token).'&email='.urlencode($this->email);

        return [[
            'subject' => 'Reset your password',
            'greeting' => 'Hello {name},',
            'lines' => [
                'We received a request to reset the password for your Nexus Peptides account.',
                'This link expires in {expiry} minutes. If you did not request a password reset, you can ignore this email; your password will remain unchanged.',
            ],
            'action' => ['label' => 'Reset password', 'url' => $resetUrl],
            'salutation' => '— Nexus Peptides | UAE Team',
        ], [
            'name' => $name,
            'email' => $this->email,
            'reset_url' => $resetUrl,
            'expiry' => (string) self::EXPIRY_MINUTES,
        ]];
    }

    public static function sample(): static
    {
        return new static('preview-token', 'customer@example.com');
    }
}
