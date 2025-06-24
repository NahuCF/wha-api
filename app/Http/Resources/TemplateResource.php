<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TemplateResource extends JsonResource
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
            'language' => $this->language,
            'category' => $this->category,
            'status' => $this->status,
            'allow_category_change' => $this->allow_category_change,
            'components' => [
                'header' => $this->header ? json_decode($this->header, true) : [],
                'body' => [
                    'content' => $this->body,
                    'variables' => $this->whenNotNull(json_decode($this->body_example_variables, true) ?: null),
                ],
                'footer' => $this->footer,
                'buttons' => $this->header ? json_decode($this->buttons, true) : [],
            ],
            'created_at' => $this->created_at,
        ];
    }
}
