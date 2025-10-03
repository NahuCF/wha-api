<?php

namespace App\Enums;

enum BotAction: string
{
    case UNASSIGN = 'unassign';
    case ASSIGN_USER = 'assign_user';
    case ASSIGN_BOT = 'assign_bot';
    case MESSAGE = 'message';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
