<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class InventoryTransaction extends Model {
    protected $fillable = ['product_id','quantity_before','quantity_after','type','reason'];
    protected $casts = ['quantity_before'=>'integer','quantity_after'=>'integer'];
    public function product() { return $this->belongsTo(Product::class); }
}
