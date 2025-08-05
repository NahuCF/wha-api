<?php

namespace App\Enums;

enum BroadcastStatus: string
{
    case WAITING = 'WAITING';
    case SENDING = 'SENDING';
    case FINISHED = 'FINISHED';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
