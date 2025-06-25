<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class ContactFieldValue extends Model
{
    use HasUlids;

    protected $casts = [
        'value' => 'json',
    ];

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function field()
    {
        return $this->belongsTo(ContactField::class, 'contact_field_id');
    }
}
