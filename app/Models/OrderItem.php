<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = ['order_id', 'product_id', 'product_variant_id', 'name', 'price', 'quantity', 'total_price'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /** Return-request lines attached to this item (any state). */
    public function returnRequestItems()
    {
        return $this->hasMany(ReturnRequestItem::class);
    }

    /**
     * Quantity already covered by returns: approved ones are done, requested
     * ones are being reviewed — both reduce what the customer may still ask
     * to return.
     */
    public function getReturnedQuantityAttribute(): int
    {
        return (int) $this->returnRequestItems()
            ->whereHas('returnRequest', fn ($query) => $query->whereIn('status', [
                ReturnRequest::STATUS_REQUESTED,
                ReturnRequest::STATUS_APPROVED,
            ]))
            ->get()
            ->sum('quantity');
    }
}
