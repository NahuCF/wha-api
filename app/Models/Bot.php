<?php

namespace App\Models;

use App\Enums\BotAction;
use App\Enums\BotKeywordMatchType;
use App\Enums\BotTriggerType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Bot extends Model
{
    use BelongsToTenant, HasUlids;

    protected $casts = [
        'keywords' => 'array',
        'viewport' => 'array',
        'trigger_type' => BotTriggerType::class,
        'timeout_action' => BotAction::class,
        'no_match_action' => BotAction::class,
        'end_conversation_action' => BotAction::class,
        'about_to_end_action' => BotAction::class,
    ];

    public function flows(): HasMany
    {
        return $this->hasMany(BotFlow::class);
    }

    public function activeFlow()
    {
        return $this->hasOne(BotFlow::class)->where('status', \App\Enums\FlowStatus::ACTIVE);
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

    public function aboutToEndAssignBot(): BelongsTo
    {
        return $this->belongsTo(Bot::class, 'about_to_end_assign_bot_id');
    }

    public function aboutToEndAssignUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'about_to_end_assign_user_id');
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
                    if (@preg_match('/'.$keyword.'/', $originalMessage)) {
                        return true;
                    }
                    break;
            }
        }

        return false;
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
}
