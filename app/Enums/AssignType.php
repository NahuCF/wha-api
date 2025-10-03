<?php

namespace App\Enums;

enum AssignType: string
{
    case USER = 'user';
    case BOT = 'bot';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
