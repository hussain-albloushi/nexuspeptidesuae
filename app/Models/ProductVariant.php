<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ProductVariant extends Model {
    protected $fillable = ['product_id','name','sku','price_modifier','stock_quantity','is_active'];
    public function product() { return $this->belongsTo(Product::class); }
}