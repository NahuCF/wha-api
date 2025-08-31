<?php

namespace App\Enums;

enum PhoneNumberStatus: string
{
    case CONNECTED = 'CONNECTED';
    case PENDING = 'PENDING';
    case OFFLINE = 'OFFLINE';
    case UNVERIFIED = 'UNVERIFIED';
    case FLAGGED = 'FLAGGED';
    case RESTRICTED = 'RESTRICTED';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function isActive(): bool
    {
        return $this === self::CONNECTED;
    }

    public function canSendMessages(): bool
    {
        return $this === self::CONNECTED;
    }

    public function requiresAction(): bool
    {
        return in_array($this, [self::PENDING, self::UNVERIFIED, self::FLAGGED, self::RESTRICTED], true);
    }
}
