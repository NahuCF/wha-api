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
            'is_pinned' => $this->isPinnedByUser(auth()->id()),
            'contact' => $this->whenLoaded('contact', fn () => new ContactResource($this->contact)),
            'assigned_user' => $this->whenLoaded('assignedUser', fn () => new UserResource($this->assignedUser)),
            'waba' => $this->whenLoaded('waba', fn () => new WabaResource($this->waba)),
            'phone_number' => $this->whenLoaded('phoneNumber', fn () => new PhoneNumberResource($this->phoneNumber)),
            'to_phone' => $this->to_phone,
            'last_message' => $this->whenLoaded('latestMessage', fn () => new MessageResource($this->latestMessage)),
            'last_message_at' => $this->last_message_at,
            'is_initiated' => $this->expires_at !== null,
            'expires_at' => $this->expires_at,
            'started_at' => $this->started_at,
            'matching_message' => $this->when($this->hasAttribute('matching_message_data'), fn () => $this->getAttribute('matching_message_data')),
        ];
    }
}
