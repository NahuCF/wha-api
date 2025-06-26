<?php

namespace App\Http\Resources;

use App\Enums\ContactFieldType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactFieldResource extends JsonResource
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
            'internal_name' => $this->internal_name,
            'type' => $this->type,
            'is_mandatory' => $this->is_mandatory,
            'is_active' => $this->is_active,
            'is_primary_field' => $this->is_primary_field,
            'options' => $this->when($this->type == ContactFieldType::SELECT->value, $this->options),
        ];
    }
}
