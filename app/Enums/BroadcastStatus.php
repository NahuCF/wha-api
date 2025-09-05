<?php

namespace App\Enums;

enum BroadcastStatus: string
{
    case QUEUED = 'queued';
    case SCHEDULED = 'scheduled';
    case SENDING = 'sending';
    case SENT = 'sent';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
    case COMPLETED = 'completed';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function canCancel(): bool
    {
        return match ($this) {
            self::SCHEDULED, self::SENDING => true,
            default => false,
        };
    }

    public function canRetry(): bool
    {
        return $this === self::FAILED;
    }

    public function isActive(): bool
    {
        return in_array($this, [self::SCHEDULED, self::SENDING]);
    }

    public function isCompleted(): bool
    {
        return in_array($this, [self::SENT, self::FAILED, self::CANCELLED]);
    }
}
