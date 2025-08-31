<?php

namespace App\Enums;

enum QualityRating: string
{
    case GREEN = 'GREEN';
    case YELLOW = 'YELLOW';
    case RED = 'RED';
    case UNKNOWN = 'UNKNOWN';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
