<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTemplateRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:512'],

            // Now body is inside components
            'components' => ['required', 'array'],

            // Body
            'components.body' => ['required'],
            'components.body.variables' => ['sometimes'],
            'components.body.text' => ['required', 'string', 'max:1024'],

            // Language and category
            'language_id' => ['required', 'integer', Rule::exists('landlord.languages', 'id')],
            'template_category_id' => ['required', 'integer', Rule::exists('landlord.template_categories', 'id')],

            // Header (optional)
            'components.header' => ['sometimes', 'array'],
            'components.header.type' => ['required_with:components.header', 'string', Rule::in(['TEXT'])],
            'components.header.text' => ['required_with:components.header', 'string'],

            // Buttons (optional)
            'components.buttons' => ['sometimes', 'array'],
            'components.buttons.*.type' => ['required', Rule::in(['QUICK_REPLY', 'PHONE_NUMBER', 'STATIC_URL', 'DYNAMIC_URL'])],
            'components.buttons.*.text' => ['required', 'string'],
            'components.buttons.*.phone_number' => ['required_if:components.buttons.*.type,PHONE_NUMBER', 'string'],
            'components.buttons.*.phone_number_prefix' => ['required_if:components.buttons.*.type,PHONE_NUMBER', 'string'],
        ];
    }

    protected function hasPhoneNumberButton(): bool
    {
        $buttons = $this->input('buttons', []);
        foreach ($buttons as $button) {
            if (($button['type'] ?? null) === 'PHONE_NUMBER') {
                return true;
            }
        }

        return false;
    }
}
