<?php

namespace App\Models;

use App\Enums\MessageDirection;
use App\Enums\MessageSource;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Message extends Model
{
    use BelongsToTenant, HasFactory, HasUlids;

    protected $casts = [
        'direction' => MessageDirection::class,
        'type' => MessageType::class,
        'status' => MessageStatus::class,
        'source' => MessageSource::class,
        'media' => 'array',
        'interactive_data' => 'array',
        'location_data' => 'array',
        'contacts_data' => 'array',
        'variables' => 'array',
        'errors' => 'array',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'reply_to_message_id');
    }

    public function replies()
    {
        return $this->hasMany(Message::class, 'reply_to_message_id');
    }

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(Broadcast::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function isInbound(): bool
    {
        return $this->direction === MessageDirection::INBOUND;
    }

    public function isOutbound(): bool
    {
        return $this->direction === MessageDirection::OUTBOUND;
    }

    public function isText(): bool
    {
        return $this->type === MessageType::TEXT;
    }

    public function isMedia(): bool
    {
        return $this->type?->isMedia() ?? false;
    }

    public function isTemplate(): bool
    {
        return $this->type === MessageType::TEMPLATE;
    }

    public function isInteractive(): bool
    {
        return $this->type?->isInteractive() ?? false;
    }

    public function isReply(): bool
    {
        return $this->reply_to_message_id !== null;
    }

    public function hasReplies(): bool
    {
        return $this->replies()->exists();
    }

    public function markAsDelivered(): void
    {
        $this->update([
            'status' => MessageStatus::DELIVERED,
            'delivered_at' => now(),
        ]);
    }

    public function markAsRead(): void
    {
        $this->update([
            'status' => MessageStatus::READ,
            'read_at' => now(),
        ]);
    }

    public function markAsFailed(?array $errors = null): void
    {
        $this->update([
            'status' => MessageStatus::FAILED,
            'failed_at' => now(),
            'errors' => $errors,
        ]);
    }

    public function scopeInbound($query)
    {
        return $query->where('direction', MessageDirection::INBOUND->value);
    }

    public function scopeOutbound($query)
    {
        return $query->where('direction', MessageDirection::OUTBOUND->value);
    }

    public function scopeUnread($query)
    {
        return $query->inbound()->whereNull('read_at');
    }

    public function scopeByType($query, string|MessageType $type)
    {
        if ($type instanceof MessageType) {
            return $query->where('type', $type->value);
        }

        return $query->where('type', $type);
    }

    public function scopeWithReplies($query)
    {
        return $query->with(['replies' => function ($q) {
            $q->orderBy('created_at', 'asc');
        }]);
    }

    public function scopeIsReply($query)
    {
        return $query->whereNotNull('reply_to_message_id');
    }

    public function scopeNotReply($query)
    {
        return $query->whereNull('reply_to_message_id');
    }

    public function scopeDelivered($query)
    {
        return $query->whereIn('status', [MessageStatus::DELIVERED->value, MessageStatus::READ->value]);
    }

    public function scopeBySource($query, string|MessageSource $source)
    {
        if ($source instanceof MessageSource) {
            return $query->where('source', $source->value);
        }

        return $query->where('source', $source);
    }

    public function scopeByBroadcast($query, string $broadcastId)
    {
        return $query->where('broadcast_id', $broadcastId);
    }
}
