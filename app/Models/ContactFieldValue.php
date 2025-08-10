<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class ContactFieldValue extends Model
{
    use BelongsToTenant, HasUlids;

    protected $casts = [
        'value' => 'json',
    ];

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function field()
    {
        return $this->belongsTo(ContactField::class, 'contact_field_id')
            ->withoutGlobalScopes()
            ->where(function ($query) {
                $query->whereNull('tenant_id')
                    ->orWhere('tenant_id', $this->tenant_id);
            });
    }
}
