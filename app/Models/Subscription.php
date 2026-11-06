<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Subscription extends Model {
    protected $fillable = ['user_id','subscription_plan_id','stripe_subscription_id','status','trial_ends_at','current_period_ends_at','canceled_at','amount'];
    public function user() { return $this->belongsTo(User::class); }
    public function plan() { return $this->belongsTo(SubscriptionPlan::class,'subscription_plan_id'); }
}