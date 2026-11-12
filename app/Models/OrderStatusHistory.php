<?php

namespace App\Models;

use App\Notifications\OrderStatusUpdated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Throwable;

class OrderStatusHistory extends Model
{
    // The migration creates the singular `order_status_history` table; without
    // this Eloquent would look for `order_status_histories` and 500 the query.
    protected $table = 'order_status_history';

    protected $fillable = ['order_id', 'old_status', 'new_status', 'notes'];

    protected static function booted(): void
    {
        // Every history entry is customer-facing information, so each one
        // emails the customer. A rolled-back transaction could rarely email
        // for a change that never persisted; afterCommit would avoid that but
        // never fires under the test transaction that wraps RefreshDatabase.
        static::created(function (self $history): void {
            $history->notifyCustomer();
        });
    }

    private function notifyCustomer(): void
    {
        try {
            $order = $this->order()->with('user')->first();
            $user = $order?->user;
            if (! $user || blank($user->email)) {
                return;
            }
            $user->notify(new OrderStatusUpdated($order, $this));
        } catch (Throwable $exception) {
            // Never let a mail outage break an admin status change.
            Log::error('Unable to send the order update email.', ['order_status_history_id' => $this->id, 'exception' => $exception->getMessage()]);
        }
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
