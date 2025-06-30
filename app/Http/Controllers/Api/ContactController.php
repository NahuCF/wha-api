<?php

namespace App\Http\Controllers\Api;

use Carbon\Carbon;
use App\Models\Contact;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Enums\FilterOperator;
use Illuminate\Validation\Rule;
use App\Services\ContactService;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\Cache\Store;
use App\Http\Resources\ContactResource;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\StoreContactRequest;
use App\Http\Requests\UpdateContactRequest;

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

    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:20480'],
        ]);

        $file = $request->file('file');
        $path = 'contact-imports/' . tenant()->id . '/' . $file->getClientOriginalName();

        $s3Path = Storage::disk('s3')->putFileAs('', $file, $path);

        return response()->json([
            'message' => 'File uploaded successfully',
            'path' => $s3Path,
            'url' => Storage::disk('s3')->url($s3Path),
        ]);
    }
}
