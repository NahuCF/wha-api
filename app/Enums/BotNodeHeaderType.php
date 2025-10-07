<?php

namespace App\Enums;

enum BotNodeHeaderType: string
{
    case TEXT = 'text';
    case IMAGE = 'image';
    case VIDEO = 'video';
    case DOCUMENT = 'document';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
