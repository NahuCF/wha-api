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
            'components' => [
                'header' => [
                    'type' => $this->header_type,
                    'content' => $this->header_text,
                    'media_url' => $this->whenNotNull($this->header_media_url)
                ],
                'body' => [
                    'content' => $this->body,
                    'variables' => $this->whenNotNull(json_decode($this->body_example_variables, true) ?: null)
                ],
                'footer' => $this->footer,
                'buttons' => ButtonResource::collection($this->buttons)
            ]
        ];
    }
}
