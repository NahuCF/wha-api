<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantOtp extends Model
{
    protected $guarded = [];

    protected $casts = [
        'expire_at' => 'datetime',
        'sent_at' => 'datetime',
    ];
}
