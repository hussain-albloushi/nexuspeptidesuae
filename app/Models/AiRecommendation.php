<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiRecommendation extends Model
{
    protected $table = 'ai_recommendations';

    protected $fillable = [
        'title', 'description', 'category', 'priority', 'status',
        'reasoning', 'suggested_action', 'expected_impact', 'supporting_data',
        'relevant_products', 'relevant_categories', 'stability_signature',
        'internal_notes', 'implemented_at',
    ];

    protected $casts = [
        'relevant_products' => 'array',
        'relevant_categories' => 'array',
        'implemented_at' => 'datetime',
    ];

    public const STATUSES = ['new', 'reviewed', 'in_progress', 'completed', 'dismissed'];

    public const PRIORITIES = ['critical', 'high', 'medium', 'low'];

    public const CATEGORIES = [
        'Increase Revenue', 'Improve Profitability', 'Improve Product Performance',
        'Inventory Optimization', 'Marketing Opportunities', 'Pricing Opportunities',
        'Customer Retention', 'Sales Growth', 'Reduce Expenses',
    ];
}
