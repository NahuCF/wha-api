<?php

namespace App\Services;

use App\Enums\ContactFieldType;
use App\Enums\FilterOperator;
use App\Models\Contact;
use App\Models\ContactField;
use App\Models\ContactFieldValue;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ContactService
{
    public function index(
        array $columns = ['*'],
        array $filters = [],
        ?string $search = null,
        bool $paginate = true,
        int $rowsPerPage = 10
    ) {
        $query = Contact::query()
            ->select($columns)
            ->when($search, function ($query) use ($search) {
                $query->whereHas('fieldValues', function ($q) use ($search) {
                    $q->whereRaw('value::text ILIKE ?', ['%'.strtolower($search).'%']);
                });
            })
            ->when(! empty($filters), function ($query) use ($filters) {
                foreach ($filters as $filter) {
                    $query->whereHas('fieldValues', function ($q) use ($filter) {
                        $column = 'value::text';
                        $operator = FilterOperator::from($filter['operator']);
                        $isValueRequired = ! in_array($operator, [FilterOperator::IS_EMPTY, FilterOperator::IS_NOT_EMPTY]);
                        $values = $isValueRequired ? (array) ($filter['value'] ?? []) : [];

                        $q->where('contact_field_id', $filter['contact_field_id']);

                        $q->where(function ($or) use ($operator, $values, $column) {
                            if (in_array($operator, [FilterOperator::IS_EMPTY, FilterOperator::IS_NOT_EMPTY])) {
                                match ($operator) {
                                    FilterOperator::IS_EMPTY => $or->orWhere(function ($sub) {
                                        $sub->whereRaw("value::text = '\"\"'")
                                            ->orWhereRaw("jsonb_typeof(value) = 'array' AND jsonb_array_length(value) = 0");
                                    }),
                                    FilterOperator::IS_NOT_EMPTY => $or->orWhere(function ($sub) {
                                        $sub->whereRaw("value::text != '\"\"'")
                                            ->orWhereRaw("jsonb_typeof(value) = 'array' AND jsonb_array_length(value) > 0");
                                    }),
                                };
                            } else {
                                foreach ($values as $value) {
                                    match ($operator) {
                                        FilterOperator::IS => $or->orWhereRaw("$column = ?", [json_encode($value)]),
                                        FilterOperator::IS_NOT => $or->orWhereRaw("$column != ?", [json_encode($value)]),
                                        FilterOperator::CONTAINS => $or->orWhereRaw("$column ILIKE ?", ['%'.$value.'%']),
                                        FilterOperator::NOT_CONTAINS => $or->orWhereRaw("$column NOT ILIKE ?", ['%'.$value.'%']),
                                        FilterOperator::STARTS_WITH => $or->orWhereRaw("$column ILIKE ?", [$value.'%']),
                                        FilterOperator::ENDS_WITH => $or->orWhereRaw("$column ILIKE ?", ['%'.$value]),
                                    };
                                }
                            }
                        });
                    });
                }
            })
            ->orderBy('id', 'desc');

        return $paginate ? $query->paginate($rowsPerPage) : $query->get();
    }

    public function store(array $fields)
    {
        // Check duplicate contact by phone
        $phoneField = ContactField::withoutGlobalScopes()
            ->where(function ($query) {
                $query->whereNull('tenant_id')
                    ->orWhere('tenant_id', tenant('id'));
            })
            ->where('name', 'Phone')
            ->firstOrFail();

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
                'tenant_id' => tenant('id'),
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
        $allFields = ContactField::withoutGlobalScopes()
            ->where(function ($query) {
                $query->whereNull('tenant_id')
                    ->orWhere('tenant_id', tenant('id'));
            })
            ->get()->keyBy('id');

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
