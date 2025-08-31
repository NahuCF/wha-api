<?php

namespace App\Models;

use App\Enums\CodeVerificationStatus;
use App\Enums\PhoneNumberStatus;
use App\Enums\QualityRating;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhoneNumber extends Model
{
    use HasUlids;

    protected $casts = [
        'quality_rating' => QualityRating::class,
        'code_verification_status' => CodeVerificationStatus::class,
        'is_registered' => 'boolean',
        'status' => PhoneNumberStatus::class,
    ];

    protected $hidden = [
        'pin',
    ];

    public function waba(): BelongsTo
    {
        return $this->belongsTo(Waba::class);
    }
}
