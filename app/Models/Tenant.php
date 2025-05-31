<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains, HasUlids;

    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'website',
            'business_name',
            'email',
            'database',
        ];
    }
}
