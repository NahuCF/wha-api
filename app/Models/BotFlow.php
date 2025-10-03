<?php

namespace App\Models;

use App\Enums\FlowConditionType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotFlow extends Model
{
    use HasUlids;

    protected $casts = [
        'condition_type' => FlowConditionType::class,
    ];

    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    public function sourceNode(): BelongsTo
    {
        return $this->belongsTo(BotNode::class, 'source_node_id', 'node_id')
            ->where('bot_id', $this->bot_id);
    }

    public function targetNode(): BelongsTo
    {
        return $this->belongsTo(BotNode::class, 'target_node_id', 'node_id')
            ->where('bot_id', $this->bot_id);
    }
}
