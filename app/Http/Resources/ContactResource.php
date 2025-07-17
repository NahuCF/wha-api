<?php

namespace App\Http\Resources;

use App\Enums\ContactFieldType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactResource extends JsonResource
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
            'fields' => $this->fieldValues->map(fn ($field) => [
                'field_value_id' => $field->field->id,
                'name' => $field->field->name,
                'value' => ContactFieldType::arrayTypeValues()->contains($field->field->type) ? explode(',', $field->value) : $field->value,
            ]),
        ];
    }
}
