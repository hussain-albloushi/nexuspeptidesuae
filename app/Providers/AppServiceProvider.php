<?php

namespace App\Providers;

use App\Services\SiteSettings;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Commands, queues and scheduled tasks have no HTTP middleware, so
        // apply the website clock here as well as in ApplySiteSettings.
        app(SiteSettings::class)->applyRuntimeConfig();

        VerifyEmail::toMailUsing(fn (object $notifiable, string $url): MailMessage => $this->verificationMail($notifiable, $url));
    }

    /**
     * Branded email verification message. The verification URL is generated
     * by the framework, so only the copy and branding are customised here.
     */
    private function verificationMail(object $notifiable, string $url): MailMessage
    {
        $name = ($notifiable->name ?? null) ?: 'there';

        return (new MailMessage)
            ->subject('Verify your email address')
            ->greeting('Welcome, '.$name.'!')
            ->line('Thanks for joining Nexus Peptides | UAE Team! One quick step remains to secure your account and activate your member benefits:')
            ->line('Verifying your email confirms your identity, protects your orders, and makes sure delivery and tracking updates reach your inbox.')
            ->action('Verify email address', $url)
            ->line('This verification link expires in 60 minutes and can only be used once.')
            ->line('If you did not create an account, no action is needed — you can safely ignore this email.')
            ->salutation('— Nexus Peptides | UAE Team');
    }
}
