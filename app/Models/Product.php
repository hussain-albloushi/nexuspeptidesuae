<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = [
        'category_id','name','slug','description','price','compare_at_price','cost_price',
        'sku','is_active','featured_image_url','requires_subscription','stock_quantity',
        'stock_status','low_stock_threshold','featured','visibility','seo_title','seo_description'
    ];
    protected $casts = [
        'price'=>'decimal:2','compare_at_price'=>'decimal:2','cost_price'=>'decimal:2',
        'is_active'=>'boolean','requires_subscription'=>'boolean','featured'=>'boolean',
        'stock_quantity'=>'integer','low_stock_threshold'=>'integer'
    ];
    public function category() { return $this->belongsTo(Category::class); }
    public function variants() { return $this->hasMany(ProductVariant::class); }
    public function images() { return $this->hasMany(ProductImage::class); }
    public function transactions() { return $this->hasMany(InventoryTransaction::class); }
    public function isLowStock() { return $this->stock_quantity > 0 && $this->stock_quantity <= ($this->low_stock_threshold ?? 5); }

    public function adjustStock($delta, $type = 'adjustment', $reason = null) {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($delta, $type, $reason) {
            $product = static::whereKey($this->getKey())->lockForUpdate()->firstOrFail();
            $before = (int) $product->stock_quantity;
            $after = $before + (int) $delta;
            if ($after < 0) throw new \Exception('Insufficient stock');
            $threshold = (int) ($product->low_stock_threshold ?? 5);
            $product->update([
                'stock_quantity' => $after,
                'stock_status' => $after <= 0 ? 'out_of_stock' : ($after <= $threshold ? 'low_stock' : 'in_stock'),
            ]);
            InventoryTransaction::create(['product_id' => $product->id, 'quantity_before' => $before, 'quantity_after' => $after, 'type' => $type, 'reason' => $reason]);
            $this->setRawAttributes($product->getAttributes(), true);
            return $after;
        });
    }

    public static function generateSlug($name) {
        $slug = \Illuminate\Support\Str::slug($name);
        $count = static::where('slug', $slug)->count();
        if ($count) $slug .= '-' . ($count + 1);
        return $slug;
    }
}
