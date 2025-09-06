<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BroadcastResource extends JsonResource
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
            'name' => $this->name,
            'follow_whatsapp_business_policy' => $this->follow_whatsapp_business_policy,
            'template_id' => $this->template_id,
            'template' => TemplateResource::make($this->whenLoaded('template')),
            'user' => UserResource::make($this->whenLoaded('user')),
            'groups' => GroupResource::collection($this->whenLoaded('groups')),
            'phone_number' => PhoneNumberResource::make($this->whenLoaded('phoneNumber')),
            'status' => $this->status,
            'recipients_count' => $this->recipients_count,
            'sent_count' => $this->sent_count,
            'delivered_count' => $this->delivered_count,
            'readed_count' => $this->readed_count,
            'replied_count' => $this->replied_count,
            'failed_count' => $this->failed_count,
            'scheduled_at' => $this->scheduled_at,
            'sent_at' => $this->sent_at,
        ];
    }
}
