<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'email' => $this->email,
            'cellphone_number' => $this->cellphone_number,
            'cellphone_prefix' => $this->cellphone_prefix,
            'cellphone' => $this->cellphone_prefix.$this->cellphone_number,
            'teams' => TeamResource::collection($this->whenLoaded('teams')),
            'role' => new RoleResource($this->roles->first()),
            'permissions' => PermissionResource::collection($this->whenLoaded('permissions')),
            'permission_names' => $this->whenLoaded('permission_names'),
            'is_deleted' => $this->trashed(),
            'status' => $this->status,
        ];
    }
}
