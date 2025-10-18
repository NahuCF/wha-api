<?php

namespace App\Enums;

enum TemplateCategory: string
{
    case AUTHENTICATION = 'AUTHENTICATION';
    case MARKETING = 'MARKETING';
    case UTILITY = 'UTILITY';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
