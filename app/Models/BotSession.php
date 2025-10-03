<?php

namespace App\Models;

use App\Enums\BotSessionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotSession extends Model
{
    use HasUlids;

    protected $fillable = [
        'tenant_id',
        'bot_id',
        'conversation_id',
        'contact_id',
        'current_node_id',
        'status',
        'variables',
        'history',
        'last_interaction_at',
        'timeout_at',
    ];

    protected $casts = [
        'variables' => 'array',
        'history' => 'array',
        'status' => BotSessionStatus::class,
        'last_interaction_at' => 'datetime',
        'timeout_at' => 'datetime',
    ];

    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function currentNode(): ?BotNode
    {
        if (! $this->current_node_id) {
            return null;
        }

        return $this->bot->nodes()
            ->where('node_id', $this->current_node_id)
            ->first();
    }

    public function setVariable(string $name, $value): void
    {
        $variables = $this->variables ?? [];
        $variables[$name] = $value;
        $this->variables = $variables;
        $this->save();
    }

    public function getVariable(string $name, $default = null)
    {
        return $this->variables[$name] ?? $default;
    }

    public function addToHistory(string $nodeId, array $data = []): void
    {
        $history = $this->history ?? [];
        $history[] = [
            'node_id' => $nodeId,
            'timestamp' => now(),
            'data' => $data,
        ];
        $this->history = $history;
        $this->save();
    }

    public function isExpired(): bool
    {
        return $this->timeout_at && $this->timeout_at->isPast();
    }

    public function markAsWaiting(?int $minutes = null): void
    {
        $minutes = $minutes ?? $this->bot->wait_time_minutes ?? 5;

        $this->update([
            'status' => BotSessionStatus::WAITING,
            'timeout_at' => now()->addMinutes($minutes),
            'last_interaction_at' => now(),
        ]);
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'status' => BotSessionStatus::COMPLETED,
            'last_interaction_at' => now(),
        ]);
    }

    public function markAsTimeout(): void
    {
        $this->update([
            'status' => BotSessionStatus::TIMEOUT,
        ]);
    }
}
