<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WabaResource extends JsonResource
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
            'business_id' => $this->business_id,
            'meta_waba_id' => $this->meta_waba_id,
            'name' => $this->name,
            'currency' => $this->currency,
            'timezone_id' => $this->timezone_id,
            'message_template_namespace' => $this->message_template_namespace,
        ];
    }
}
