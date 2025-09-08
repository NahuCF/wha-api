<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Group extends Model
{
    use BelongsToTenant, HasUlids;

    protected $cast = [
        'contact_ids' => 'array',
        'contacts_count' => 'integer',
    ];

    public function contacts()
    {
        return $this->belongsToMany(Contact::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function broadcasts()
    {
        return $this->belongsToMany(Broadcast::class, 'broadcast_group');
    }
}
