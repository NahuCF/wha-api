<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class ContactImportHistory extends Model
{
    use HasUlids;

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
