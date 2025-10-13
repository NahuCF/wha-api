<?php

namespace App\Enums;

enum BotSessionStatus: string
{
    case ACTIVE = 'active';
    case WAITING = 'waiting';
    case COMPLETED = 'completed';
    case ABANDONED = 'abandoned';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
