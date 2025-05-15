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
            'components' => [
                'header' => [
                    'type' => $this->header_type,
                    'content' => $this->header_text,
                    'media_url' => $this->header_media_url
                ],
                'body' => [
                    'content' => $this->body,
                    'variables' => json_decode($this->body_example_variables)
                ],
                'footer' => $this->footer,
                'buttons' => ButtonResource::collection($this->buttons)
            ]
        ];
    }
}
