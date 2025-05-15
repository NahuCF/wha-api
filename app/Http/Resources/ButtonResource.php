<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ButtonResource extends JsonResource
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
            'type' => $this->type,
            'text' => $this->text,
            'url' => $this->whenNotNull($this->text),
            'phone_prefix' => $this->whenNotNull($this->phone_prefix),
            'phone_number' => $this->whenNotNull($this->phone_number),
            'index' => $this->index,
        ];
    }
}
