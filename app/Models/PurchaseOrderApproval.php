<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PurchaseOrderApproval extends Model {
    protected $fillable = ['purchase_order_id','user_id','action','reason'];
}
