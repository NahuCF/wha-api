<?php

namespace App\Enums;

enum ComparisonOperator: string
{
    case EQUAL = 'equal';
    case NOT_EQUAL = 'not_equal';
    case LESS_THAN = 'less_than';
    case LESS_THAN_OR_EQUAL = 'less_than_or_equal';
    case GREATER_THAN = 'greater_than';
    case GREATER_THAN_OR_EQUAL = 'greater_than_or_equal';
    case IS_EMPTY = 'is_empty';
    case IS_NOT_EMPTY = 'is_not_empty';
    case CONTAINS = 'contains';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function evaluate($leftValue, $rightValue): bool
    {
        $left = $leftValue !== null ? (string) $leftValue : null;
        $right = $rightValue !== null ? (string) $rightValue : null;

        return match ($this) {
            self::EQUAL => $left === $right,
            self::NOT_EQUAL => $left !== $right,
            self::LESS_THAN => is_numeric($left) && is_numeric($right) && (float) $left < (float) $right,
            self::LESS_THAN_OR_EQUAL => is_numeric($left) && is_numeric($right) && (float) $left <= (float) $right,
            self::GREATER_THAN => is_numeric($left) && is_numeric($right) && (float) $left > (float) $right,
            self::GREATER_THAN_OR_EQUAL => is_numeric($left) && is_numeric($right) && (float) $left >= (float) $right,
            self::IS_EMPTY => $left === null || $left === '',
            self::IS_NOT_EMPTY => $left !== null && $left !== '',
            self::CONTAINS => $left !== null && $right !== null && str_contains($left, $right),
        };
    }
}
