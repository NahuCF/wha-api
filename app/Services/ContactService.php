<?php

namespace App\Services;

use App\Enums\ContactFieldType;
use App\Models\Contact;
use App\Models\ContactField;
use App\Models\ContactFieldValue;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ContactService
{
    public function store(array $fields)
    {
        // Check duplicate contact by phone
        $phoneField = ContactField::where('name', 'Phone')->firstOrFail();
        $phoneValue = collect($fields)->firstWhere('id', $phoneField->id)['value'];

        if (ContactFieldValue::whereJsonContains('value', $phoneValue)->exists()) {
            throw ValidationException::withMessages(['contact' => 'Contact already exists']);
        }

        // Validate types & special user lookup
        $this->assertFieldTypes($fields);

        $contact = Contact::create();

        $values = collect($fields)
            ->map(fn ($f) => [
                'id' => Str::ulid(),
                'contact_id' => $contact->id,
                'contact_field_id' => $f['id'],
                'value' => json_encode($f['value']),
                'created_at' => now(),
                'updated_at' => now(),
            ])->toArray();

        ContactFieldValue::insert($values);

        return $contact;
    }

    public function update(array $fields, Contact $contact)
    {
        // Check duplicate contact by phone
        $phoneField = ContactField::where('name', 'Phone')->firstOrFail();
        $phoneValue = collect($fields)->firstWhere('id', $phoneField->id)['value'];

        $exitsContact = ContactFieldValue::query()
            ->where('contact_id', '!=', $contact->id)
            ->whereJsonContains('value', $phoneValue)
            ->exists();

        if ($exitsContact) {
            throw ValidationException::withMessages(['contact' => 'Contact already exists']);
        }

        // Validate types & special user lookup
        $this->assertFieldTypes($fields);

        $values = collect($fields)
            ->map(fn ($f) => [
                'id' => Str::ulid(),
                'contact_id' => $contact->id,
                'contact_field_id' => $f['id'],
                'value' => json_encode($f['value']),
                'created_at' => now(),
                'updated_at' => now(),
            ])->toArray();

        ContactFieldValue::where('contact_id', $contact->id)->delete();
        ContactFieldValue::insert($values);

        return $contact;
    }

    protected function assertFieldTypes(array $fields): void
    {
        $allFields = ContactField::all()->keyBy('id');

        foreach ($fields as $f) {
            $field = $allFields[$f['id']];
            $value = $f['value'];
            $type = $field->type;

            if ((ContactFieldType::arrayTypeValues()->contains($type) && ! is_array($value))
                || (ContactFieldType::NUMBER->value == $type && ! is_numeric($value))
                || (ContactFieldType::TEXT->value == $type && ! is_string($value))
                || (ContactFieldType::SWITCH->value == $type && ! is_bool($value))
                || (ContactFieldType::DATE->value == $type && ! $this->isValidDateString($value))) {
                throw ValidationException::withMessages([
                    'fields' => 'Invalid field value',
                ]);
            }

            if (ContactFieldType::USER->value == $type) {
                if (! is_string($value)) {
                    throw ValidationException::withMessages([
                        'fields' => 'Invalid field value',
                    ]);
                }

                $user = User::query()
                    ->where('email', 'ILIKE', $value)
                    ->orWhere('name', 'ILIKE', $value)
                    ->orWhere('id', $value)
                    ->first();

                if (! $user) {
                    throw ValidationException::withMessages([
                        'fields' => 'Invalid field value',
                    ]);
                }
            }
        }
    }

    private function isValidDateString(string $value): bool
    {
        try {
            Carbon::parse($value);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
