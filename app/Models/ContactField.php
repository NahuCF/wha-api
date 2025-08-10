<?php

namespace App\Models;

use App\Enums\ContactFieldType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class ContactField extends Model
{
    use BelongsToTenant, HasUlids;

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
