<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReturnRequest extends Model
{
    public const STATUS_REQUESTED = 'requested';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = ['order_id', 'user_id', 'reason', 'status', 'refund_amount', 'decision_notes'];

    protected $casts = ['refund_amount' => 'decimal:2'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany */
    public function items()
    {
        return $this->hasMany(ReturnRequestItem::class);
    }

    /**
     * The refund this request will issue if fully honoured: item prices only,
     * shipping and cash-on-delivery fees are not refunded on returns.
     */
    public function refundAmount(): float
    {
        return round($this->items()->with('orderItem')->get()
            ->sum(fn (ReturnRequestItem $item): float => (float) $item->orderItem->price * $item->quantity), 2);
    }
}
