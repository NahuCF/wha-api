<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BotEdgeResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'edge_id' => $this->edge_id,
            'source_node_id' => $this->source_node_id,
            'target_node_id' => $this->target_node_id,
            'source_handle' => $this->source_handle,
            'target_handle' => $this->target_handle,
            'condition_type' => $this->condition_type,
            'condition_value' => $this->condition_value,
            'data' => $this->data,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
