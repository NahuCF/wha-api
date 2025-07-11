<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Template extends Model
{
    use HasUlids;

    protected $guarded = [];

    public const CATEGORY_TYPES = [
        'AUTHENTICATION',
        'MARKETING',
        'UTILITY',
    ];

    public const BUTTON_TYPES = [
        'QUICK_REPLY',
        'PHONE_NUMBER',
        'STATIC_URL',
        'DYNAMIC_URL',
    ];

    public const HEADER_TYPES = [
        'TEXT',
        'IMAGE',
        'VIDEO',
        'DOCUMENT',
        'LOCATION',
    ];

    protected $casts = [
        'header' => 'array',
        'buttons' => 'array',
        'body_example_variables' => 'array',
    ];
}
