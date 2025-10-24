<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';
    case GRACE_PERIOD = 'grace_period';
    case SUSPENDED = 'suspended';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::ACTIVE => 'Active',
            self::CANCELLED => 'Cancelled',
            self::EXPIRED => 'Expired',
            self::GRACE_PERIOD => 'Grace Period',
            self::SUSPENDED => 'Suspended',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::ACTIVE, self::GRACE_PERIOD]);
    }
}
