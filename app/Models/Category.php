<?php
namespace App\Models;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
class Category extends Model {
    use SoftDeletes;
    protected $fillable = ['name','slug','parent_id','description','is_active','image_url','sort_order'];
    protected $casts = ['is_active'=>'boolean'];
    public function products() { return $this->hasMany(Product::class); }
    public function parent() { return $this->belongsTo(self::class, 'parent_id'); }
    public function children() { return $this->hasMany(self::class, 'parent_id'); }
    public static function generateSlug($name) {
        $slug = \Illuminate\Support\Str::slug($name);
        $count = static::where('slug',$slug)->count();
        return $count ? $slug . '-' . ($count+1) : $slug;
    }
}
