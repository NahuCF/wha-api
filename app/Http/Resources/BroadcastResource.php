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
            'send_at' => $this->send_at,
            'follow_whatsapp_business_policy' => $this->follow_whatsapp_business_policy,
            'template_id' => $this->template_id,
            'template' => TemplateResource::make($this->whenLoaded('template')),
            'group_id' => $this->group_id,
            'group' => GroupResource::make($this->whenLoaded('group')),
            'user_id' => $this->user_id,
            'user' => UserResource::make($this->whenLoaded('user')),
        ];
    }
}
