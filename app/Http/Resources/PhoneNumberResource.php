<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class PhoneNumberResource extends JsonResource
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
            'display_phone_number' => $this->display_phone_number,
            'verified_name' => $this->verified_name,
            'quality_rating' => $this->quality_rating,
            'code_verification_status' => $this->code_verification_status,
            'is_verified' => $this->code_verification_status?->isVerified() ?? false,
            'is_registered' => $this->is_registered,
            'status' => $this->status,
            'can_send_messages' => $this->status?->canSendMessages() ?? false,
            'about' => $this->about,
            'address' => $this->address,
            'description' => $this->description,
            'email' => $this->email,
            'vertical' => $this->vertical,
            'websites' => $this->websites,
            'picture_url' => $this->profile_picture_path
                ? Storage::disk('s3')->url($this->profile_picture_path)
                : null,
        ];
    }
}
