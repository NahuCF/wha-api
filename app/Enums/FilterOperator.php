<?php

namespace App\Enums;

enum FilterOperator: string
{
    case IS = 'is';
    case IS_NOT = 'is_not';
    case CONTAINS = 'contains';
    case NOT_CONTAINS = 'not_contains';
    case STARTS_WITH = 'starts_with';
    case ENDS_WITH = 'ends_with';
    case IS_EMPTY = 'is_empty';
    case IS_NOT_EMPTY = 'is_not_empty';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
