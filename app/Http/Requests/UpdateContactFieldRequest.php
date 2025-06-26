<?php

namespace App\Http\Requests;

use App\Enums\ContactFieldType;
use App\Models\ContactField;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContactFieldRequest extends FormRequest
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
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'internal_name' => ['sometimes', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(ContactField::types())],
            'is_mandatory' => ['required', 'boolean'],
            'options' => [
                Rule::requiredIf(fn () => $this->input('type') === ContactFieldType::SELECT->value),
                'array',
                'min:1',
            ],
        ];
    }
}
