<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class PasswordSetToken extends Model
{
    use HasUlids;

    protected $table = 'password_set_tokens';

    public $timestamps = false;

    protected $keyType = 'string';

    public $incrementing = false;


    protected $casts = [
        'created_at' => 'datetime',
    ];
}
