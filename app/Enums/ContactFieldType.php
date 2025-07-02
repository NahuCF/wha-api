<?php

namespace App\Enums;

enum ContactFieldType: string
{
    case SELECT = 'SELECT';
    case NUMBER = 'NUMBER';
    case TEXT = 'TEXT';
    case MULTI_TEXT = 'MULTI_TEXT';
    case USER = 'USER';
    case SWITCH = 'SWITCH';
    case DATE = 'DATE';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function arrayTypeValues()
    {
        return collect([
            self::MULTI_TEXT->value,
        ]);
    }
}
