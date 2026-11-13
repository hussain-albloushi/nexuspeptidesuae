<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiReport extends Model
{
    protected $table = 'ai_reports';

    protected $fillable = [
        'type', 'period_start', 'period_end', 'content', 'scheduled',
    ];

    protected $casts = [
        'period_start' => 'string',
        'period_end' => 'string',
    ];

    public const TYPES = [
        'daily_report', 'weekly_report', 'monthly_report',
        'product_performance', 'sales_performance', 'profitability',
        'inventory', 'business_opportunities',
    ];
}
