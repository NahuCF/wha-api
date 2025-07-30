<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
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
            'is_internal' => $this->is_internal,
            'permissions' => PermissionResource::collection($this->whenLoaded('permissions')),
            'user' => UserResource::make($this->whenLoaded('user')),
        ];
    }
}
