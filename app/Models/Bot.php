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
        'timeout_action' => BotAction::class,
        'no_match_action' => BotAction::class,
        'end_conversation_action' => BotAction::class,
        // Bot-specific default se
        'default_no_match_action' => BotAction::class,
        'default_timeout_action' => BotAction::class,
        'default_expire_action' => BotAction::class,
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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_user_id');
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

    // Bot default settings relationships
    public function defaultNoMatchUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'default_no_match_user_id');
    }

    public function defaultNoMatchBot(): BelongsTo
    {
        return $this->belongsTo(Bot::class, 'default_no_match_bot_id');
    }

    public function defaultTimeoutUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'default_timeout_user_id');
    }

    public function defaultTimeoutBot(): BelongsTo
    {
        return $this->belongsTo(Bot::class, 'default_timeout_bot_id');
    }

    public function defaultExpireUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'default_expire_user_id');
    }

    public function defaultExpireBot(): BelongsTo
    {
        return $this->belongsTo(Bot::class, 'default_expire_bot_id');
    }

    public function matchesKeyword(string $message): bool
    {
        if ($this->trigger_type !== BotTriggerType::KEYWORD) {
            return false;
        }

        if (empty($this->keywords)) {
            return false;
        }

        $originalMessage = trim($message);
        $lowerMessage = strtolower($originalMessage);

        // Check each keyword entry
        foreach ($this->keywords as $keywordData) {
            $keyword = $keywordData['keyword'] ?? '';
            $matchType = $keywordData['match_type'] ?? 'exact';
            $caseSensitive = $keywordData['case_sensitive'] ?? false;
            
            if (empty($keyword)) {
                continue;
            }

            // Prepare message and keyword based on case sensitivity
            $testMessage = $caseSensitive ? $originalMessage : $lowerMessage;
            $testKeyword = $caseSensitive ? trim($keyword) : strtolower(trim($keyword));

            switch ($matchType) {
                case 'exact':
                case BotKeywordMatchType::EXACT->value:
                    if ($testMessage === $testKeyword) {
                        return true;
                    }
                    break;

                case 'contains':
                case BotKeywordMatchType::CONTAINS->value:
                    if (str_contains($testMessage, $testKeyword)) {
                        return true;
                    }
                    break;

                case 'regex':
                case BotKeywordMatchType::REGEX->value:
                    if (@preg_match('/' . $keyword . '/', $originalMessage)) {
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
