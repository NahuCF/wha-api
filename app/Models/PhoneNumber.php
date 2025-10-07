<?php

namespace App\Models;

use App\Enums\CodeVerificationStatus;
use App\Enums\PhoneNumberStatus;
use App\Enums\QualityRating;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class PhoneNumber extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = [
        'waba_id',
        'meta_id',
        'display_phone_number',
        'verified_name',
        'quality_rating',
        'code_verification_status',
        'pin',
        'is_registered',
        'status',
        'about',
        'address',
        'description',
        'email',
        'vertical',
        'websites',
        'profile_picture_path',
        'profile_picture_handle',
        'profile_updated_at',
    ];

    protected $casts = [
        'quality_rating' => QualityRating::class,
        'code_verification_status' => CodeVerificationStatus::class,
        'is_registered' => 'boolean',
        'status' => PhoneNumberStatus::class,
        'websites' => 'array',
        'profile_updated_at' => 'datetime',
    ];

    protected $hidden = [
        'pin',
    ];

    /**
     * Business verticals allowed by WhatsApp
     */
    const ALLOWED_VERTICALS = [
        'AUTOMOTIVE',
        'BEAUTY_SPA_SALON',
        'APPAREL_FASHION',
        'EDUCATION',
        'ENTERTAINMENT',
        'EVENT_PLANNING_SERVICE',
        'FINANCE_BANKING',
        'FOOD_GROCERY',
        'PUBLIC_SERVICE',
        'HOTEL_LODGING',
        'MEDICAL_HEALTH',
        'NONPROFIT',
        'PROFESSIONAL_SERVICES',
        'SHOPPING_RETAIL',
        'TRAVEL_TRANSPORTATION',
        'RESTAURANT',
        'OTHER',
    ];

    public function waba(): BelongsTo
    {
        return $this->belongsTo(Waba::class);
    }
}
