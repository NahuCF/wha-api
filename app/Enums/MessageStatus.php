<?php

namespace App\Enums;

enum MessageStatus: string
{
    case PENDING = 'pending';
    case SENT = 'sent';
    case DELIVERED = 'delivered';
    case READ = 'read';
    case FAILED = 'failed';
    case DELETED = 'deleted';

    public function isSuccessful(): bool
    {
        return in_array($this, [
            self::SENT,
            self::DELIVERED,
            self::READ,
        ]);
    }

    public function isFinal(): bool
    {
        return in_array($this, [
            self::READ,
            self::FAILED,
            self::DELETED,
        ]);
    }

    public function canRetry(): bool
    {
        return $this === self::FAILED;
    }

    public function priority(): int
    {
        return match ($this) {
            self::DELETED => 0,
            self::FAILED => 1,
            self::PENDING => 2,
            self::SENT => 3,
            self::DELIVERED => 4,
            self::READ => 5,
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function default(): self
    {
        return self::PENDING;
    }
}
