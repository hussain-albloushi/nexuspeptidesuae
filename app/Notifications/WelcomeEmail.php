<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeEmail extends Notification
{
    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $base = rtrim((string) config('app.url'), '/');

        return (new MailMessage)
            ->subject('Welcome to Nexus Peptides')
            ->greeting('Welcome aboard, '.(($notifiable->name ?? null) ?: 'there').'!')
            ->line('Thank you for creating your Nexus Peptides account. You now have access to everything our store has to offer:')
            ->line('• Faster checkout with your saved billing and delivery details')
            ->line('• Track every order from purchase to your doorstep')
            ->line('• Wishlist your favourite peptides and pick up where you left off')
            ->line('• Exclusive 18+ member pricing and promotions')
            ->action('Start shopping', $base.'/peptide-shop')
            ->line('We also sent you a separate email with a verification link — confirming it secures your account and keeps order updates coming your way.')
            ->line('If you have any questions, our support team is one reply away at info@nexuspeptides.com or via the Help Center on our website.')
            ->salutation('— Nexus Peptides | UAE Team');
    }
}
