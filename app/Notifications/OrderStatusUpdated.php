<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderStatusUpdated extends Notification
{
    public function __construct(public Order $order, public OrderStatusHistory $history) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->order;
        $history = $this->history;

        $mail = (new MailMessage)
            ->subject('Order #'.$order->order_number.' — update')
            ->greeting('Hello '.(($notifiable->name ?? null) ?: 'there').',');

        if ($history->old_status !== $history->new_status) {
            $mail->line('Your order status changed from '.$this->label($history->old_status).' to '.$this->label($history->new_status).'.');
        } else {
            $mail->line('We added an update to your order.');
        }

        if (filled($history->notes)) {
            $mail->line('Note: '.$history->notes);
        }

        if (filled($order->tracking_number)) {
            $mail->line('Tracking number: '.$order->tracking_number);
        }

        $mail->line('Order total: AED '.number_format((float) $order->total_amount, 2));

        return $mail
            ->action('View order history', rtrim((string) config('app.url'), '/').'/my-orders')
            ->line('Thank you for shopping with us!')
            ->salutation('— Nexus Peptides | UAE Team');
    }

    private function label(?string $status): string
    {
        return $status === null ? '-' : mb_convert_case(str_replace('_', ' ', $status), MB_CASE_TITLE);
    }
}
