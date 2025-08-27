<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
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
            'meta_id' => $this->meta_id,
            'is_solved' => $this->is_solved,
            'unread_count' => $this->unread_count,
            'is_expired' => $this->isExpired(),
            'contact' => new ContactResource($this->whenLoaded('contact')),
            'assigned_user' => new UserResource($this->whenLoaded('assignedUser')),
            'waba' => new WabaResource($this->whenLoaded('waba')),
            'last_message' => new MessageResource($this->whenLoaded('latestMessage')),
            'last_message_at' => $this->last_message_at,
            'expires_at' => $this->expires_at,
        ];
    }
}
