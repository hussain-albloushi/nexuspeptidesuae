<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PurchaseOrderItem extends Model {
    protected $table = 'purchase_order_items';
    protected $fillable = ['purchase_order_id','product_id','product_name_snapshot','quantity','unit_cost','discount','tax','line_total'];
    protected $casts = ['quantity'=>'integer','unit_cost'=>'decimal:2','discount'=>'decimal:2','tax'=>'decimal:2','line_total'=>'decimal:2'];
    public function order() { return $this->belongsTo(SupplierPurchaseOrder::class,'purchase_order_id'); }
    public function product() { return $this->belongsTo(Product::class); }
}
