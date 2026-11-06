<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SupplierProduct extends Model {
    protected $fillable = ['supplier_id','product_id','supplier_product_code','purchase_price','currency','min_order_qty','lead_time_days','preferred','last_purchase_price','historical_prices'];
    protected $casts = ['historical_prices'=>'array','preferred'=>'boolean'];
    public function supplier() { return $this->belongsTo(Supplier::class); }
    public function product() { return $this->belongsTo(Product::class); }
}
