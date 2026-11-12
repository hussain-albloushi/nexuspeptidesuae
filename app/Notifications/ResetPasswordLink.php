<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordLink extends Notification
{
    public function __construct(public string $token, public string $email) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim((string) config('app.url'), '/').'/reset-password?token='.urlencode($this->token).'&email='.urlencode($this->email);

        return (new MailMessage)
            ->subject('Reset your password')
            ->greeting('Hello '.(($notifiable->name ?? null) ?: 'there').',')
            ->line('We received a request to reset the password for your Nexus Peptides account.')
            ->action('Reset password', $url)
            ->line('This link expires in 60 minutes. If you did not request a password reset, you can ignore this email; your password will remain unchanged.')
            ->salutation('— Nexus Peptides | UAE Team');
    }
}
