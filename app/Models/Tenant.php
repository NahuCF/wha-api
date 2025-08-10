<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant
{
    use HasUlids, SoftDeletes;

    protected $fillable = [
        'company_name',
        'id',
    ];

    public static function getCustomColumns(): array
    {
        return [
            'company_name',
            'id',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function templates(): HasMany
    {
        return $this->hasMany(Template::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function groups(): HasMany
    {
        return $this->hasMany(Group::class);
    }

    public function broadcasts(): HasMany
    {
        return $this->hasMany(Broadcast::class);
    }

    public function businesses(): HasMany
    {
        return $this->hasMany(Business::class);
    }
}
