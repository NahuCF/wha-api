<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    use HasUlids;

    public function fieldValues()
    {
        return $this->hasMany(ContactFieldValue::class);
    }
}
