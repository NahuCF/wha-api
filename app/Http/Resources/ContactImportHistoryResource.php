<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactImportHistoryResource extends JsonResource
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
            'user' => new UserResource($this->whenLoaded('user')),
            'import_type' => $this->import_type,
            'added_contacts_count' => $this->added_contacts_count,
            'updated_contacts_count' => $this->updated_contacts_count,
            'error_contacts_count' => $this->error_contacts_count,
            'total_contacts_count' => $this->added_contacts_count + $this->error_contacts_count + $this->updated_contacts_count,
            'status' => $this->status,
            'created_at' => $this->created_at,
        ];
    }
}
