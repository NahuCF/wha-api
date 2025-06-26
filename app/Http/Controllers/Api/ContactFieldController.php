<?php

namespace App\Http\Controllers\Api;

use App\Enums\ContactFieldType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContactFieldRequest;
use App\Http\Requests\UpdateContactFieldRequest;
use App\Http\Resources\ContactFieldResource;
use App\Models\ContactField;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ContactFieldController extends Controller
{
    public function index(Request $request)
    {
        $input = $request->validate([
            'rows_per_page' => ['sometimes', 'integer'],
        ]);

        $rowsPerPage = data_get($input, 'rows_per_page', 10);

        $contactFields = ContactField::query()
            ->orderBy('is_primary_field', 'desc')
            ->paginate($rowsPerPage);

        return ContactFieldResource::collection($contactFields);
    }

    public function types()
    {
        return response()->json([
            'data' => ContactField::types(),
        ]);
    }

    public function store(StoreContactFieldRequest $request)
    {
        $input = $request->validated();

        $user = Auth::user();

        $name = data_get($input, 'name');
        $type = data_get($input, 'type');
        $options = data_get($input, 'options');
        $internalName = data_get($input, 'internal_name');

        $dataToStore = [
            'name' => $name,
            'internal_name' => $internalName ?? str_replace(' ', '_', strtolower($name)),
            'type' => data_get($input, 'type'),
            'is_mandatory' => data_get($input, 'is_mandatory'),
            'user_id' => $user->id,
        ];

        if ($type == ContactFieldType::SELECT->value) {
            $dataToStore['options'] = $options;
        }

        $contactField = ContactField::query()
            ->create($dataToStore);

        return new ContactFieldResource($contactField);
    }

    public function update(UpdateContactFieldRequest $request, ContactField $field)
    {
        $input = $request->validated();

        $name = data_get($input, 'name');
        $type = data_get($input, 'type');
        $options = data_get($input, 'options');
        $internalName = data_get($input, 'internal_name');

        $dataToUpdate = [
            'name' => $name,
            'internal_name' => $internalName ?? str_replace(' ', '_', strtolower($name)),
            'type' => data_get($input, 'type'),
            'is_mandatory' => data_get($input, 'is_mandatory'),
        ];

        if ($type == ContactFieldType::SELECT->value) {
            $dataToUpdate['options'] = $options;
        }

        $field->update($dataToUpdate);

        return new ContactFieldResource($field);
    }

    public function destroy(ContactField $field)
    {
        if ($field->is_primary_field) {
            throw ValidationException::withMessages([
                'primary_field' => 'This contact field cannot be deleted',
            ]);
        }

        $field->delete();

        return response()->noContent();
    }

    public function changeStatus(Request $request, ContactField $contactField)
    {
        $input = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $isActive = data_get($input, 'is_active');

        $contactField->update([
            'is_active' => $isActive,
        ]);

        return new ContactFieldResource($contactField);
    }

    public function changeMandatory(Request $request, ContactField $contactField)
    {
        $input = $request->validate([
            'is_mandatory' => ['required', 'boolean'],
        ]);

        $isMandatory = data_get($input, 'is_mandatory');

        $contactField->update([
            'is_mandatory' => $isMandatory,
        ]);

        return new ContactFieldResource($contactField);
    }
}
