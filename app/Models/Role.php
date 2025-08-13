<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
