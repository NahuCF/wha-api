<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
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
            'website' => $this->website,
            'email' => $this->email,
            'business_name' => $this->business_name,
            'verified_email' => $this->verified_email,
            'verified_whatsapp' => $this->verified_whatsapp,
            'filled_basic_information' => $this->filled_basic_information,
        ];
    }
}
