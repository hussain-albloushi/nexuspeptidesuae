<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class OrderStatusHistory extends Model {
    protected $fillable = ['order_id','old_status','new_status','notes'];
    public function order() { return $this->belongsTo(Order::class); }
}
