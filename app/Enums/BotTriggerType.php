<?php

namespace App\Enums;

enum BotTriggerType: string
{
    case KEYWORD = 'keyword';
    case ANY_MESSAGE = 'any_message';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
