<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Order extends Model {
    protected $fillable = [
        'user_id','cart_id','order_number','status','subtotal','tax_amount','discount_amount',
        'shipping_amount','total_amount','payment_method','shipping_address','billing_address',
        'notes','tracking_number','payment_status','shipping_status','internal_notes'
    ];
    protected $casts = ['subtotal'=>'decimal:2','tax_amount'=>'decimal:2','discount_amount'=>'decimal:2','shipping_amount'=>'decimal:2','total_amount'=>'decimal:2'];
    public function user() { return $this->belongsTo(User::class); }
    public function cart() { return $this->belongsTo(Cart::class); }
    public function items() { return $this->hasMany(OrderItem::class); }
    public function statusHistory() { return $this->hasMany(OrderStatusHistory::class); }

    public static $allowedStatusTransitions = [
        'pending' => ['processing','cancelled'],
        'processing' => ['shipped','cancelled'],
        'shipped' => ['delivered','cancelled'],
        'delivered' => ['cancelled'],
        'cancelled' => [],
    ];

    public function canTransitionTo($newStatus) {
        return in_array($newStatus, self::$allowedStatusTransitions[$this->status] ?? []);
    }

    public function transitionStatus($newStatus, $notes = null) {
        if (!$this->canTransitionTo($newStatus)) return false;
        $old = $this->status;
        $this->update(['status'=>$newStatus]);
        OrderStatusHistory::create([
            'order_id'=>$this->id, 'old_status'=>$old, 'new_status'=>$newStatus, 'notes'=>$notes
        ]);
        return true;
    }
}
