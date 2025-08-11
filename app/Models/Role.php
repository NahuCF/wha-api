<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Role extends SpatieRole
{

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
