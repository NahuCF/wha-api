<?php

namespace App\Enums;

enum FlowConditionType: string
{
    case ALWAYS = 'always';           // No condition, always follow (used for all non-question nodes)
    case OPTION = 'option';           // Match user's selected option (used for question nodes)
    case DEFAULT = 'default';         // Fallback when no option matches (used for question nodes)

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
