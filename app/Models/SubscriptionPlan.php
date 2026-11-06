<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SubscriptionPlan extends Model {
    protected $fillable = ['name','description','price','interval','trial_days','features','stripe_price_id','is_active'];
}