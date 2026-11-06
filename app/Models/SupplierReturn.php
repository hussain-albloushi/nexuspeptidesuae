<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SupplierReturn extends Model {
    protected $fillable = ['purchase_order_id','grn_id','returned_qty','reason','notes','inventory_reduced','documents_path','status'];
    protected $casts = ['inventory_reduced'=>'decimal:4','returned_qty'=>'integer'];
    public function purchaseOrder() { return $this->belongsTo(SupplierPurchaseOrder::class); }
    public function grn() { return $this->belongsTo(GoodsReceivedNote::class); }
}
