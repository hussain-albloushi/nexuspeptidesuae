<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class GoodsReceivedNote extends Model {
    protected $fillable = ['purchase_order_id','po_number','reference','delivery_date','accepted_qty','damaged_qty','rejected_qty','total_line_item_qty','notes','batch_numbers','expiry_dates','documents_path'];
    protected $casts = ['batch_numbers'=>'array','expiry_dates'=>'array'];
    public function purchaseOrder() { return $this->belongsTo(SupplierPurchaseOrder::class); }
}
