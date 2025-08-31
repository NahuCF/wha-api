<?php

namespace App\Enums;

enum CodeVerificationStatus: string
{
    case NOT_VERIFIED = 'NOT_VERIFIED';
    case VERIFIED = 'VERIFIED';

    public function isVerified(): bool
    {
        return $this === self::VERIFIED;
    }
}
