<?php

namespace App\Models;

use App\Models\Scopes\WabaIdScope;
use App\Traits\HasWabaId;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Conversation extends Model
{
    use BelongsToTenant, HasFactory, HasUlids, HasWabaId;

    protected $casts = [
        'is_solved' => 'boolean',
        'last_message_at' => 'datetime',
        'expires_at' => 'datetime',
        'unread_count' => 'integer',
    ];

    protected static function booted()
    {
        static::addGlobalScope(new WabaIdScope);
    }

    public function waba(): BelongsTo
    {
        return $this->belongsTo(Waba::class);
    }

    public function phoneNumber(): BelongsTo
    {
        return $this->belongsTo(PhoneNumber::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at', 'desc');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ConversationActivity::class)->orderBy('created_at', 'desc');
    }

    public function latestMessage()
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function markAsRead(): void
    {
        $this->update(['unread_count' => 0]);
    }

    public function incrementUnreadCount(): void
    {
        $this->increment('unread_count');
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function notStarted(): bool
    {
        return $this->expires_at === null;
    }

    public function isActive(): bool
    {
        return ! $this->is_solved && ! $this->isExpired();
    }

    public function scopeActive($query)
    {
        return $query->where('is_solved', false)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    public function scopeWithUnread($query)
    {
        return $query->where('unread_count', '>', 0);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function pinnedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_pins')
            ->withPivot(['position', 'pinned_at']);
    }

    public function isPinnedBy(User $user): bool
    {
        return $this->pinnedByUsers()->where('user_id', $user->id)->exists();
    }

    public function pinFor(User $user, int $position = 0): void
    {
        $this->pinnedByUsers()->syncWithoutDetaching([
            $user->id => ['position' => $position, 'pinned_at' => now()],
        ]);
    }

    public function unpinFor(User $user): void
    {
        $this->pinnedByUsers()->detach($user->id);
    }

    public function scopePinnedBy($query, User $user)
    {
        return $query->whereHas('pinnedByUsers', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        });
    }
}
