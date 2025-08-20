<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class User extends Authenticatable
{
    use BelongsToTenant, HasApiTokens, HasRoles, HasUlids, Notifiable, SoftDeletes;

    protected $guarded = [];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function teams()
    {
        return $this->belongsToMany(Team::class);
    }

    public function loadPermissionNames(): self
    {
        $role = $this->roles()->first(); // since user has only one role

        $permissions = $role
            ? $role->permissions()->pluck('name')
            : collect(); // return empty if no role assigned

        $this->setRelation('permission_names', $permissions);

        return $this;
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

}
