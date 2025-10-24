<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class UserWaba extends Pivot
{
    protected $table = 'user_waba';

    public $incrementing = false;

    public $timestamps = false;


    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function waba(): BelongsTo
    {
        return $this->belongsTo(Waba::class);
    }
}
