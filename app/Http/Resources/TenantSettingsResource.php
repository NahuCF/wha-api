<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantSettingsResource extends JsonResource
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
            'language' => $this->language ?? 'en',
            'timezone' => $this->timezone,
            'working_days' => $this->working_days,
            'special_days' => $this->special_days,
            'closed_days' => $this->closed_days,
            'away_message' => $this->away_message,
            'is_currently_open' => $this->isWithinWorkingHours(),
        ];
    }
}
