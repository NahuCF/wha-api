<?php

namespace App\Enums;

enum ContactImportType: string
{
    case ADD = 'ADD';
    case ADD_AND_REPALCE = 'ADD_AND_REPLACE';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
