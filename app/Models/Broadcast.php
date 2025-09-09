<?php

namespace App\Models;

use App\Enums\BroadcastStatus;
use App\Traits\HasWabaId;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Broadcast extends Model
{
    use BelongsToTenant, HasUlids, HasWabaId;

    protected $casts = [
        'status' => BroadcastStatus::class,
        'recipients' => 'array',
        'variables' => 'array',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'send_to_all_numbers' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function template()
    {
        return $this->belongsTo(Template::class);
    }

    public function phoneNumber()
    {
        return $this->belongsTo(PhoneNumber::class);
    }

    public function groups()
    {
        return $this->belongsToMany(Group::class, 'broadcast_group');
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function getTotalRecipientsCountAttribute()
    {
        $directRecipients = is_array($this->recipients) ? count($this->recipients) : 0;

        $groupContactsCount = $this->groups()->withCount('contacts')->get()->sum('contacts_count');

        return $directRecipients + $groupContactsCount;
    }

    public function getAllContactsAttribute()
    {
        $contactIds = collect($this->recipients ?? []);

        $groupContactIds = $this->groups()
            ->with('contacts:id')
            ->get()
            ->pluck('contacts')
            ->flatten()
            ->pluck('id');

        $contactIds = $contactIds->merge($groupContactIds);

        return $contactIds->unique()->values();
    }
}
