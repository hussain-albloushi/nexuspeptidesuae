<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    protected $fillable = [
        'key',
        'label',
        'subject',
        'greeting',
        'body',
        'action_label',
        'salutation',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
