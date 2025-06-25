<?php

namespace App\Models;

use App\Enums\ContactFieldType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class ContactField extends Model
{
    use HasUlids;

    public static function types(): array
    {
        return ContactFieldType::values();
    }

    protected $casts = [
        'options' => 'array',
    ];

    public function values()
    {
        return $this->hasMany(ContactFieldValue::class);
    }
}
