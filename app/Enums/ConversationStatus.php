<?php

namespace App\Enums;

enum ConversationStatus: string
{
    case ACTIVE = 'active';
    case ARCHIVED = 'archived';
    case CLOSED = 'closed';
    case EXPIRED = 'expired';
    case PENDING = 'pending';

    public function canSendMessage(): bool
    {
        return in_array($this, [
            self::ACTIVE,
            self::PENDING,
        ]);
    }

    public function canBeReactivated(): bool
    {
        return in_array($this, [
            self::ARCHIVED,
            self::EXPIRED,
        ]);
    }

    public function isFinished(): bool
    {
        return in_array($this, [
            self::CLOSED,
            self::EXPIRED,
        ]);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
