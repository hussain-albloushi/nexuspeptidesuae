<?php
namespace Database\Seeders; use App\Models\SubscriptionPlan; use Illuminate\Database\Seeder;
class SubscriptionPlanSeeder extends Seeder { public function run(): void {
    SubscriptionPlan::create(['name'=>'Basic Wellness','price'=>29,'interval'=>'month','features'=>'[]']);
    SubscriptionPlan::create(['name'=>'Premium Wellness','price'=>59,'interval'=>'month','features'=>'["Priority shipping","Premium variants"]']);
}}