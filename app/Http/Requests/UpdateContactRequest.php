<?php

namespace App\Http\Requests;

use App\Rules\NoDuplicateFieldIds;
use App\Rules\AllMandatoryFieldsPresent;
use Illuminate\Foundation\Http\FormRequest;

class UpdateContactRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules()
    {
        return [
            'fields' => ['required', 'array', 'min:1', new NoDuplicateFieldIds],
            'fields.*.id' => ['required', 'exists:contact_fields,id'],
            'fields.*.value' => ['required'],
            'fields' => [new AllMandatoryFieldsPresent],
        ];
    }

    public function messages(): array
    {
        return [
            'fields.*.value.required' => 'You must provide a value for every field',
            'fields.*.id.exists' => 'Field does not exist',
        ];
    }
}
