<?php

namespace App\Enums;

enum ContactImportStatus: string
{
    case PENDING = 'PENDING';
    case PROCESSING = 'PROCESSING';
    case COMPLETED = 'COMPLETED';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
