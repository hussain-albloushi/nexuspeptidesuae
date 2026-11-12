<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use App\Notifications\OrderStatusUpdated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OrderNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_status_change_emails_the_customer_with_the_transition(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $order = $this->order($user);

        $order->transitionStatus('processing', 'Packed');

        Notification::assertSentTo($user, OrderStatusUpdated::class, function (OrderStatusUpdated $notification): bool {
            return $notification->history->old_status === 'pending'
                && $notification->history->new_status === 'processing'
                && $notification->history->notes === 'Packed';
        });
    }

    public function test_every_history_entry_emails_the_customer(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $order = $this->order($user, ['status' => 'delivered']);

        OrderStatusHistory::create(['order_id' => $order->id, 'old_status' => 'delivered', 'new_status' => 'delivered', 'notes' => 'Payment received in full and confirmed.']);

        Notification::assertSentTo($user, OrderStatusUpdated::class);
    }

    public function test_history_rows_for_orders_without_a_customer_do_not_error(): void
    {
        $user = User::factory()->create();
        $order = $this->order($user);
        $user->delete();

        $history = OrderStatusHistory::create(['order_id' => $order->id, 'old_status' => 'pending', 'new_status' => 'processing', 'notes' => null]);

        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertSame('processing', $history->new_status);
    }

    public function test_order_update_email_renders_the_order_number_and_link(): void
    {
        $user = User::factory()->create(['name' => 'Test Customer']);
        $order = $this->order($user);
        $history = $order->statusHistory()->create(['old_status' => 'pending', 'new_status' => 'shipped', 'notes' => 'Left with courier']);

        $mail = (new OrderStatusUpdated($order, $history))->toMail($user);

        $this->assertSame('Order #'.$order->order_number.' — update', $mail->subject);
        $this->assertStringContainsString('/my-orders', $mail->actionUrl);
        $this->assertStringContainsString('Tracking number: TRACK-42', json_encode($mail->introLines));
    }

    private function order(User $user, array $attributes = []): Order
    {
        $order = Order::create(array_merge([
            'user_id' => $user->id, 'order_number' => 'NP-'.fake()->unique()->numerify('######'),
            'status' => 'pending', 'payment_method' => 'cod', 'shipping_address' => json_encode(['city' => 'Dubai']),
            'subtotal' => 100, 'total_amount' => 100, 'tracking_number' => 'TRACK-42',
        ], $attributes));

        return $order;
    }
}
