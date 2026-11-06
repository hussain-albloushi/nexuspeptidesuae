<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Coupon extends Model {
    protected $fillable = [
        'code','type','value','min_order_amount','max_discount','usage_limit',
        'per_customer_limit','usage_count','starts_at','expires_at','is_active',
        'product_restrictions','category_restrictions'
    ];
    protected $casts = [
        'is_active'=>'boolean', 'product_restrictions'=>'array', 'category_restrictions'=>'array',
        'usage_limit'=>'integer', 'per_customer_limit'=>'integer', 'usage_count'=>'integer'
    ];

    public function isValid() {
        if (!$this->is_active) return false;
        if ($this->starts_at && now()->lt($this->starts_at)) return false;
        if ($this->expires_at && now()->gt($this->expires_at)) return false;
        return true;
    }

    public function canApply($userId = null, $orderTotal = 0, $items = []) {
        if (!$this->isValid()) return false;
        if ($this->min_order_amount && $orderTotal < $this->min_order_amount) return false;
        if ($this->usage_limit !== null && $this->usage_count >= $this->usage_limit) return false;
        if ($userId && $this->per_customer_limit !== null) {
            // Count usage by user (approximate from orders)
            $used = \App\Models\Order::where('user_id', $userId)->count();
            if ($used >= $this->per_customer_limit) return false;
        }
        // Product / category restrictions not fully enforced in quick mode
        return true;
    }
}
