<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Contact extends Model
{
    use BelongsToTenant, HasUlids;

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
