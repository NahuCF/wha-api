<?php

namespace App\Http\Requests;

use App\Models\Template;
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

            // Language and category
            'language_id' => ['required', Rule::exists('landlord.template_languages', 'id')],
            'category' => ['required', Rule::in(Template::CATEGORY_TYPES)],

            // Now body is inside components
            'components' => ['required', 'array'],

            // Body
            'components.body' => ['required'],
            'components.body.variables' => ['sometimes'],
            'components.body.text' => ['required', 'string', 'max:1024'],

            // Header (optional)
            'components.header' => ['sometimes', 'array'],
            'components.header.type' => ['required_with:components.header', 'string', Rule::in(Template::HEADER_TYPES)],
            'components.header.text' => ['required_if:components.header.type,TEXT', 'string'],
            'components.header.media_url' => ['required_if:components.header.type,IMAGE,VIDEO,DOCUMENT', 'url'],
            'components.header.location_latitude' => ['required_if:components.header.type,LOCATION', 'numeric'],
            'components.header.location_longitude' => ['required_if:components.header.type,LOCATION', 'numeric'],
            'components.header.location_name' => ['required_if:components.header.type,LOCATION', 'string'],
            'components.header.location_address' => ['required_if:components.header.type,LOCATION', 'string'],

            // Buttons (optional)
            'components.buttons' => ['sometimes', 'array'],
            'components.buttons.*.type' => ['required', Rule::in(Template::BUTTON_TYPES)],
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
