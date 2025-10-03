<?php

namespace App\Models;

use App\Enums\BotAction;
use App\Enums\BotKeywordMatchType;
use App\Enums\BotTriggerType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Bot extends Model
{
    use BelongsToTenant, HasUlids, SoftDeletes;

    protected $casts = [
        'is_active' => 'boolean',
        'keywords' => 'array',
        'viewport' => 'array',
        'trigger_type' => BotTriggerType::class,
        'keyword_match_type' => BotKeywordMatchType::class,
        'timeout_action' => BotAction::class,
        'no_match_action' => BotAction::class,
        'end_conversation_action' => BotAction::class,
    ];

    public function nodes(): HasMany
    {
        return $this->hasMany(BotNode::class);
    }

    public function flows(): HasMany
    {
        return $this->hasMany(BotFlow::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(BotSession::class);
    }

    public function timeoutAssignBot(): BelongsTo
    {
        return $this->belongsTo(Bot::class, 'timeout_assign_bot_id');
    }

    public function timeoutAssignUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'timeout_assign_user_id');
    }

    public function noMatchAssignBot(): BelongsTo
    {
        return $this->belongsTo(Bot::class, 'no_match_assign_bot_id');
    }

    public function noMatchAssignUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'no_match_assign_user_id');
    }

    public function endConversationAssignBot(): BelongsTo
    {
        return $this->belongsTo(Bot::class, 'end_conversation_assign_bot_id');
    }

    public function endConversationAssignUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'end_conversation_assign_user_id');
    }

    public function matchesKeyword(string $message): bool
    {
        if ($this->trigger_type !== BotTriggerType::KEYWORD) {
            return false;
        }

        if (empty($this->keywords)) {
            return false;
        }

        $message = strtolower(trim($message));

        foreach ($this->keywords as $keyword) {
            $keyword = strtolower(trim($keyword));

            switch ($this->keyword_match_type) {
                case BotKeywordMatchType::EXACT:
                    if ($message === $keyword) {
                        return true;
                    }
                    break;

                case BotKeywordMatchType::CONTAINS:
                    if (str_contains($message, $keyword)) {
                        return true;
                    }
                    break;

                case BotKeywordMatchType::REGEX:
                    if (preg_match($keyword, $message)) {
                        return true;
                    }
                    break;
            }
        }

        return false;
    }

    public function getStartNode(): ?BotNode
    {
        // Find the first node - one that has no incoming edges
        $nodeWithNoIncomingEdges = $this->nodes()
            ->whereNotIn('node_id', function ($query) {
                $query->select('target_node_id')
                    ->from('bot_flows')
                    ->where('bot_id', $this->id);
            })
            ->first();

        // If found a node with no incoming edges, use it as start
        if ($nodeWithNoIncomingEdges) {
            return $nodeWithNoIncomingEdges;
        }

        // Fallback: just get the first node created
        return $this->nodes()->orderBy('created_at')->first();
    }
}
