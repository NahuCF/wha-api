<?php

namespace App\Models;

use App\Enums\FlowStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BotFlow extends Model
{
    use HasUlids;

    protected $fillable = [
        'bot_id',
        'name',
        'status',
        'total_sessions',
        'completed_sessions',
        'abandoned_sessions',
        'user_id',
        'updated_user_id',
    ];

    protected $casts = [
        'status' => FlowStatus::class,
    ];

    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    public function nodes(): HasMany
    {
        return $this->hasMany(BotNode::class);
    }

    public function edges(): HasMany
    {
        return $this->hasMany(BotEdge::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(BotSession::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_user_id');
    }

    public function getStartNode(): ?BotNode
    {
        // Find the first node - one that has no incoming edges
        $nodeWithNoIncomingEdges = $this->nodes()
            ->whereNotIn('node_id', function ($query) {
                $query->select('target_node_id')
                    ->from('bot_edges')
                    ->where('bot_flow_id', $this->id);
            })
            ->first();

        // If found a node with no incoming edges, use it as start
        if ($nodeWithNoIncomingEdges) {
            return $nodeWithNoIncomingEdges;
        }

        // Fallback: just get the first node created
        return $this->nodes()->orderBy('created_at')->first();
    }

    public function hasActiveSessions(): bool
    {
        return $this->sessions()
            ->whereIn('status', [\App\Enums\BotSessionStatus::ACTIVE, \App\Enums\BotSessionStatus::WAITING])
            ->exists();
    }

    public function getActiveSessionsCount(): int
    {
        return $this->sessions()
            ->whereIn('status', [\App\Enums\BotSessionStatus::ACTIVE, \App\Enums\BotSessionStatus::WAITING])
            ->count();
    }

    public function activate(): void
    {
        $this->bot->flows()
            ->where('id', '!=', $this->id)
            ->update(['status' => FlowStatus::DRAFT]);

        $this->update(['status' => FlowStatus::ACTIVE]);
    }

    public function deactivate(): void
    {
        $this->update(['status' => FlowStatus::DRAFT]);
    }

    public function isActive(): bool
    {
        return $this->status === FlowStatus::ACTIVE;
    }
}
