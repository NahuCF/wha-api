<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BotNodeResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'node_id' => $this->node_id,
            'type' => $this->type,
            'label' => $this->label,
            'position_x' => $this->position_x,
            'position_y' => $this->position_y,
            'data' => $this->data,
            'content' => $this->content,
            'media_url' => $this->media_url,
            'media_type' => $this->media_type,
            'options' => $this->options,
            'variable_name' => $this->variable_name,
            'use_fallback' => $this->use_fallback,
            'fallback_node_id' => $this->fallback_node_id,
            'assign_type' => $this->assign_type,
            'assign_to_user_id' => $this->assign_to_user_id,
            'assign_to_bot_id' => $this->assign_to_bot_id,
            'delay_seconds' => $this->delay_seconds,
            'voice_url' => $this->voice_url,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'location_name' => $this->location_name,
            'location_address' => $this->location_address,
            'next_nodes' => $this->next_nodes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
