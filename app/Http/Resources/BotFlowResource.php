<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BotFlowResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bot_id' => $this->bot_id,
            'name' => $this->name,
            'status' => $this->status?->value,
            'total_sessions' => $this->total_sessions,
            'completed_sessions' => $this->completed_sessions,
            'abandoned_sessions' => $this->abandoned_sessions,
            'nodes_count' => $this->whenCounted('nodes'),
            'edges_count' => $this->whenCounted('edges'),
            'active_sessions_count' => $this->when($request->has('include_active_sessions'), function () {
                return $this->getActiveSessionsCount();
            }),
            'created_by' => new UserResource($this->whenLoaded('createdBy')),
            'updated_by' => new UserResource($this->whenLoaded('updatedBy')),
            'nodes' => BotNodeResource::collection($this->whenLoaded('nodes')),
            'edges' => BotEdgeResource::collection($this->whenLoaded('edges')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
