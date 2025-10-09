<?php

namespace App\Models;

use App\Enums\FlowConditionType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotEdge extends Model
{
    use HasUlids;

    protected $table = 'bot_edges';

    protected $casts = [
        'condition_type' => FlowConditionType::class,
    ];

    protected $fillable = [
        'bot_id',
        'bot_flow_id',
        'edge_id',
        'source_node_id',
        'target_node_id',
        'condition_type',
        'condition_value',
    ];

    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    public function flow(): BelongsTo
    {
        return $this->belongsTo(BotFlow::class, 'bot_flow_id');
    }

    public function sourceNode(): BelongsTo
    {
        return $this->belongsTo(BotNode::class, 'source_node_id', 'node_id')
            ->where('bot_flow_id', $this->bot_flow_id);
    }

    public function targetNode(): BelongsTo
    {
        return $this->belongsTo(BotNode::class, 'target_node_id', 'node_id')
            ->where('bot_flow_id', $this->bot_flow_id);
    }
}
