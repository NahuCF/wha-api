<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
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
            'conversation_id' => $this->conversation_id,
            'template_id' => $this->template_id,
            'reply_to_message_id' => $this->reply_to_message_id,
            'type' => $this->type,
            'direction' => $this->direction,
            'status' => $this->status,
            'content' => $this->content,
            'media' => $this->media,
            'interactive_data' => $this->interactive_data,
            'location_data' => $this->location_data,
            'contacts_data' => $this->contacts_data,
            'context' => $this->context,
            'errors' => $this->errors,
            'from_phone' => $this->from_phone,
            'to_phone' => $this->to_phone,
            'sent_at' => $this->sent_at,
            'delivered_at' => $this->delivered_at,
            'read_at' => $this->read_at,
            'failed_at' => $this->failed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
