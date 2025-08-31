<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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

    public function defaultWaba()
    {
        return $this->belongsTo(Waba::class, 'default_waba_id');
    }

    public function defaultPhone()
    {
        return $this->belongsTo(PhoneNumber::class, 'default_phone_id');
    }

    public function wabas()
    {
        return $this->belongsToMany(Waba::class, 'user_waba')
            ->using(UserWaba::class);
    }

    public function userWabas()
    {
        return $this->hasMany(UserWaba::class);
    }

    public function pinnedConversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_pins')
            ->withPivot(['position', 'pinned_at'])
            ->orderBy('pivot_position');
    }

    public function pinConversation(Conversation $conversation, int $position = 0): void
    {
        $this->pinnedConversations()->syncWithoutDetaching([
            $conversation->id => ['position' => $position, 'pinned_at' => now()],
        ]);
    }

    public function unpinConversation(Conversation $conversation): void
    {
        $this->pinnedConversations()->detach($conversation->id);
    }

    public function reorderPinnedConversations(array $conversationIds): void
    {
        $syncData = [];
        foreach ($conversationIds as $position => $conversationId) {
            $syncData[$conversationId] = ['position' => $position, 'pinned_at' => now()];
        }
        $this->pinnedConversations()->sync($syncData);
    }
}
