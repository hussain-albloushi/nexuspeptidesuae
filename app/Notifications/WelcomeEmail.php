<?php

namespace App\Notifications;

use App\Notifications\Concerns\RendersEmailTemplate;
use Illuminate\Notifications\Notification;

class WelcomeEmail extends Notification
{
    use RendersEmailTemplate;

    public const TEMPLATE_KEY = 'welcome';

    public function __construct(private ?string $verificationUrl = null) {}

    /**
     * Built-in wording plus the placeholder context.
     *
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    public function parts(string $name = 'there'): array
    {
        $base = rtrim((string) config('app.url'), '/');
        $shopUrl = $base.'/peptide-shop';
        $verifyUrl = $this->verificationUrl ?: $shopUrl;

        return [[
            'subject' => 'Welcome to Nexus Peptides',
            'greeting' => 'Welcome aboard, {name}!',
            'lines' => [
                'Thank you for creating your Nexus Peptides account. You now have access to everything our store has to offer:',
                '• Faster checkout with your saved billing and delivery details',
                '• Track every order from purchase to your doorstep',
                '• Wishlist your favourite peptides and pick up where you left off',
                '• Exclusive 18+ member pricing and promotions',
                'Please verify your email address to secure your account and keep order updates coming your way.',
                'If you have any questions, our support team is one reply away at {support_email} or via the Help Center on our website.',
            ],
            'action' => ['label' => 'Verify your email', 'url' => $verifyUrl],
            'salutation' => '— Nexus Peptides | UAE Team',
        ], [
            'name' => $name,
            'support_email' => 'info@nexuspeptides.com',
            'shop_url' => $shopUrl,
            'verification_url' => $verifyUrl,
        ]];
    }

    public static function sample(): static
    {
        return new static;
    }
}
