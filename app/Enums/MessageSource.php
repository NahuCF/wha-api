<?php

namespace App\Enums;

enum MessageSource: string
{
    case WHATSAPP = 'whatsapp';
    case BOT = 'bot';
    case BROADCAST = 'broadcast';
    case CHAT = 'chat';

    public static function values(): array
    {
        return array_map(fn ($case) => $case->value, self::cases());
    }
}
