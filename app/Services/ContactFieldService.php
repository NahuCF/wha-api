<?php

namespace App\Services;

use App\Models\ContactField;

class ContactFieldService
{
    public function getContactFieldFromName(string $name)
    {
        return ContactField::query()
            ->where('name', $name)
            ->where('is_primary_field', true)
            ->first();
    }
}
