<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Contact extends Model
{
    use BelongsToTenant, HasUlids;

    protected $with = ['fieldValues'];

    protected $appends = ['active_broadcasts_count'];

    /**
     * Get the active broadcasts count for this contact
     */
    public function getActiveBroadcastsCountAttribute(): int
    {
        // Check if already loaded to avoid N+1 queries
        if (isset($this->attributes['active_broadcasts_count'])) {
            return $this->attributes['active_broadcasts_count'];
        }

        return DB::table('active_broadcast_contacts')
            ->where('contact_id', $this->id)
            ->value('broadcast_count') ?? 0;
    }

    public function fieldValues()
    {
        return $this->hasMany(ContactFieldValue::class);
    }

    public function groups()
    {
        return $this->belongsToMany(Group::class);
    }

    public function conversations()
    {
        return $this->hasMany(Conversation::class);
    }
}
