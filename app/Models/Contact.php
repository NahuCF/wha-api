<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    use HasUlids;

    protected $with = ['fieldValues'];

    public function fieldValues()
    {
        return $this->hasMany(ContactFieldValue::class);
    }

    public function groups()
    {
        return $this->belongsToMany(Group::class);
    }
}
