<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Arr;

class NoDuplicateFieldIds implements Rule
{
    public function passes($attribute, $value): bool
    {
        $ids = Arr::pluck($value, 'id');

        return count($ids) === count(array_unique($ids));
    }

    public function message(): string
    {
        return 'Duplicate field ID detected.';
    }
}
