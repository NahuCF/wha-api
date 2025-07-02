<?php

namespace App\Http\Controllers\Api;

use App\Enums\FilterOperator;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContactRequest;
use App\Http\Requests\UpdateContactRequest;
use App\Http\Resources\ContactResource;
use App\Models\Contact;
use App\Services\ContactService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContactController extends Controller
{
    public function index(Request $request)
    {
        $input = $request->validate([
            'rows_per_page' => ['sometimes', 'integer'],
            'search' => ['sometimes', 'string'],
            'filters' => ['sometimes', 'array'],
            'filters.*.contact_field_id' => ['required_with:filters', 'string'],
            'filters.*.operator' => ['required_with:filters', Rule::in(FilterOperator::values())],
            'filters.*.value' => ['nullable'],
        ]);

        $rowsPerPage = data_get($input, 'rows_per_page', 10);
        $search = data_get($input, 'search');
        $filters = data_get($input, 'filters', []);

        $contacts = Contact::query()
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
            ->paginate($rowsPerPage);

        return ContactResource::collection($contacts);
    }

    public function store(StoreContactRequest $request, ContactService $service)
    {
        $contact = $service->store($request->validated()['fields']);

        return new ContactResource($contact);
    }

    public function update(UpdateContactRequest $request, Contact $contact, ContactService $service)
    {
        $contact = $service->update($request->validated()['fields'], $contact);

        return new ContactResource($contact);
    }

    public function destroy(Contact $contact)
    {
        $contact->delete();

        return response()->noContent();
    }
}
