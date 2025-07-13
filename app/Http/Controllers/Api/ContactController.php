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

        $contacts = (new ContactService)->index(
            filters: $filters,
            search: $search,
            rowsPerPage: $rowsPerPage
        );

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
