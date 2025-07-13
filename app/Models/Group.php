<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    use HasUlids;

    protected $cast = [
        'contact_ids' => 'array',
    ];

    public function contacts()
    {
        return $this->belongsToMany(Contact::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
