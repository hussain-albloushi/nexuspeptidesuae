<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class OrderItem extends Model {
    protected $fillable = ['order_id','product_id','product_variant_id','name','price','quantity','total_price'];
    public function order() { return $this->belongsTo(Order::class); }
}