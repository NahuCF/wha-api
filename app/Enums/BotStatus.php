<?php

namespace App\Enums;

enum BotStatus: string
{
    case DRAFT = 'draft';
    case ACTIVE = 'active';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
