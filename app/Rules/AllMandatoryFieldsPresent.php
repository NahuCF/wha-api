<?php

namespace App\Rules;

use App\Models\ContactField;
use Illuminate\Contracts\Validation\Rule;

class AllMandatoryFieldsPresent implements Rule
{
    public function passes($attribute, $value)
    {
        // Make sure that the user its filling all mandatory fields
        $submitted = collect($value)->pluck('id');
        $mandatory = ContactField::where('is_mandatory', true)->pluck('id');
        $missing = $mandatory->diff($submitted);

        return $missing->isEmpty();
    }

    public function message()
    {
        return 'Missing mandatory fields.';
    }
}
