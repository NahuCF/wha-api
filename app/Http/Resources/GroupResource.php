<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GroupResource extends JsonResource
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
            'contacts' => ContactResource::collection($this->whenLoaded('contacts')),
            'contact_count' => $this->whenCounted('contacts'),
            'user' => new UserResource($this->whenLoaded('user')),
            'filters' => json_decode($this->filters),
            'updated_at' => $this->updated_at,
        ];
    }
}
