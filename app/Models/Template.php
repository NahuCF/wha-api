<?php

namespace App\Models;

use App\Enums\TemplateStatus;
use App\Traits\HasWabaId;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Template extends Model
{
    use BelongsToTenant, HasUlids, HasWabaId;

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
        'status' => TemplateStatus::class,
        'meta_updated_at' => 'datetime',
        'updated_count_while_approved' => 'integer',
    ];

    public function waba()
    {
        return $this->belongsTo(Waba::class);
    }

    public function getDaysSinceMetaUpdateAttribute(): int
    {
        return $this->meta_updated_at
            ? $this->meta_updated_at->diffInDays(now())
            : 0;
    }
}
