<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Notifications\Concerns\RendersEmailTemplate;
use Illuminate\Notifications\Notification;

class OrderStatusUpdated extends Notification
{
    use RendersEmailTemplate;

    public const TEMPLATE_KEY = 'order_status';

    public function __construct(public Order $order, public OrderStatusHistory $history) {}

    /**
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    public function parts(string $name = 'there'): array
    {
        $base = rtrim((string) config('app.url'), '/');
        $changed = $this->history->old_status !== $this->history->new_status;

        // Built-in default keeps the original conditional wording exactly.
        $defaultLines = [];
        $defaultLines[] = $changed
            ? 'Your order status changed from '.$this->label($this->history->old_status).' to '.$this->label($this->history->new_status).'.'
            : 'We added an update to your order.';

        if (filled($this->history->notes)) {
            $defaultLines[] = 'Note: '.$this->history->notes;
        }

        if (filled($this->order->tracking_number)) {
            $defaultLines[] = 'Tracking number: '.$this->order->tracking_number;
        }

        $defaultLines[] = 'Order total: AED '.number_format((float) $this->order->total_amount, 2);

        return [[
            'subject' => 'Order #'.$this->order->order_number.' — update',
            'greeting' => 'Hello {name},',
            'lines' => $defaultLines,
            'action' => ['label' => 'View order history', 'url' => $base.'/my-orders'],
            'salutation' => '— Nexus Peptides | UAE Team',
        ], [
            'name' => $name,
            'order_number' => (string) $this->order->order_number,
            'old_status' => $this->label($this->history->old_status),
            'new_status' => $this->label($this->history->new_status),
            'note' => (string) ($this->history->notes ?? ''),
            'tracking' => (string) ($this->order->tracking_number ?? ''),
            'total' => number_format((float) $this->order->total_amount, 2),
        ]];
    }

    /** Sample order + history for admin preview and test-send; nothing is persisted. */
    public static function sample(): static
    {
        $order = (new Order)->forceFill([
            'order_number' => 'NP-000123',
            'total_amount' => 249.00,
            'tracking_number' => 'AED-TRACK-00099',
        ]);

        $history = (new OrderStatusHistory)->forceFill([
            'old_status' => 'processing',
            'new_status' => 'shipped',
            'notes' => 'Packed and handed to the courier.',
        ]);

        return new static($order, $history);
    }

    private function label(?string $status): string
    {
        return $status === null ? '-' : mb_convert_case(str_replace('_', ' ', $status), MB_CASE_TITLE);
    }
}
