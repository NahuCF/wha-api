<?php

namespace App\Enums;

enum MessageDirection: string
{
    case INBOUND = 'inbound';
    case OUTBOUND = 'outbound';

    public function isInbound(): bool
    {
        return $this === self::INBOUND;
    }

    public function isOutbound(): bool
    {
        return $this === self::OUTBOUND;
    }

    public function resetsConversationWindow(): bool
    {
        return $this === self::INBOUND;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
