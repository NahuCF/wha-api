<?php

namespace App\Enums;

enum BotKeywordMatchType: string
{
    case EXACT = 'exact';
    case CONTAINS = 'contains';
    case REGEX = 'regex';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
