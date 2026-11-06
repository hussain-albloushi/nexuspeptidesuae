<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Supplier extends Model {
    protected $fillable = ['supplier_code','legal_name','trading_name','contact_person','email','phone','country','city','address','tax_reg_number','payment_terms','default_currency','status','notes','documents_path'];
    public function products() { return $this->belongsToMany(Product::class, 'supplier_products')->withPivot('supplier_product_code','purchase_price','currency','min_order_qty','lead_time_days','preferred','last_purchase_price','historical_prices'); }
    public function purchaseOrders() { return $this->hasMany(SupplierPurchaseOrder::class); }
}
