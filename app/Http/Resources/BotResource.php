<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BotResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_active' => $this->is_active,
            'trigger_type' => $this->trigger_type,
            'keywords' => $this->keywords,
            'wait_time_minutes' => $this->wait_time_minutes,
            'timeout_action' => $this->timeout_action,
            'timeout_assign_bot_id' => $this->timeout_assign_bot_id,
            'timeout_assign_user_id' => $this->timeout_assign_user_id,
            'timeout_message' => $this->timeout_message,
            'no_match_message' => $this->no_match_message,
            'no_match_action' => $this->no_match_action,
            'no_match_assign_bot_id' => $this->no_match_assign_bot_id,
            'no_match_assign_user_id' => $this->no_match_assign_user_id,
            'end_conversation_action' => $this->end_conversation_action,
            'end_conversation_message' => $this->end_conversation_message,
            'end_conversation_assign_bot_id' => $this->end_conversation_assign_bot_id,
            'end_conversation_assign_user_id' => $this->end_conversation_assign_user_id,
            'created_by' => new UserResource($this->whenLoaded('createdBy')),
            'updated_by' => new UserResource($this->whenLoaded('updatedBy')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
