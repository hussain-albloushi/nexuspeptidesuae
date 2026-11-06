<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Content extends Model
{
    protected $fillable = [
        'key',
        'label',
        'type',
        'body',
        'data',
        'image_path',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'data' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];
}
