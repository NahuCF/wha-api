<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasRoles, HasUlids, Notifiable, SoftDeletes;

    protected $guarded = [];

    public function teams()
    {
        return $this->belongsToMany(Team::class);
    }

    public function loadPermissionNames(): self
    {
        $this->setRelation('permission_names', $this->permissions()->pluck('name'));

        return $this;
    }
}
