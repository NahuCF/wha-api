<?php

namespace App\Http\Controllers\Api;

use App\Enums\ContactFieldType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTemplateRequest;
use App\Http\Requests\UpdateTemplateRequest;
use App\Http\Resources\TemplateResource;
use App\Models\ContactField;
use App\Models\Template;
use App\Services\TemplateLanguageService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

class TemplateController extends Controller
{
    public function index(Request $request)
    {
        $input = $request->validate([
            'rows_per_page' => ['sometimes', 'integer'],
            'name' => ['sometimes', 'string'],
            'status' => ['sometimes', 'string'],
        ]);

        $status = data_get($input, 'status');
        $rowsPerPage = data_get($input, 'rows_per_page', 10);
        $name = data_get($input, 'name');

        $templates = Template::query()
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($name, fn ($q) => $q->where('name', 'ILIKE', "%{$name}%"))
            ->paginate($rowsPerPage);

        return TemplateResource::collection($templates);
    }

    public function store(StoreTemplateRequest $request)
    {
        $input = $request->validated();

        $name = data_get($input, 'name');
        $languageId = data_get($input, 'language_id');
        $allowCategoryChange = data_get($input, 'allow_category_change', false);
        $category = data_get($input, 'category');
        $header = data_get($input, 'components.header');
        $body = data_get($input, 'components.body');
        $footer = data_get($input, 'components.footer', '');
        $buttons = data_get($input, 'components.buttons', []);

        $variables = collect(data_get($body, 'variables', []));

        $variables = $variables->map(function ($variable) {
            $contactFieldId = data_get($variable, 'contact_field_id');

            if (! $contactFieldId) {
                $name = data_get($variable, 'name');

                $contactField = ContactField::query()
                    ->create([
                        'name' => $name,
                        'internal_name' => str_replace(' ', '_', strtolower($name)),
                        'type' => ContactFieldType::TEXT,
                    ]);

                $variable['contact_field_id'] = $contactField->id;
            }

            return $variable;
        });

        if (Template::query()->where('name', $name)->exists()) {
            throw new HttpResponseException(response()->json([
                'message' => 'Template name already exists',
                'message_code' => 'templater_name_already_exists',
                'errors' => ['name' => ['Template name already exists']],
            ], 422));
        }

        $language = (new TemplateLanguageService)->getCachedLanguages()
            ->where('id', $languageId)
            ->first();

        $template = Template::create([
            'name' => $name,
            'language' => $language->code,
            'allow_category_change' => $allowCategoryChange,
            'category' => strtoupper($category),
            'body' => $body['text'],
            'body_example_variables' => json_encode($variables->toArray()),
            'footer' => $footer,
            'header' => json_encode($header ?? []),
            'buttons' => json_encode($buttons),
            'status' => 'PENDING',
        ]);

        return TemplateResource::make($template);
    }

    public function update(UpdateTemplateRequest $request, Template $template)
    {
        $input = $request->validated();

        $name = data_get($input, 'name');
        $languageId = data_get($input, 'language_id');
        $allowCategoryChange = data_get($input, 'allow_category_change', false);
        $category = data_get($input, 'category');
        $header = data_get($input, 'components.header');
        $body = data_get($input, 'components.body');
        $footer = data_get($input, 'components.footer', '');
        $buttons = data_get($input, 'components.buttons', []);

        $variables = collect(data_get($body, 'variables', []));

        $variables = $variables->map(function ($variable) {
            $contactFieldId = data_get($variable, 'contact_field_id');

            if (! $contactFieldId) {
                $name = data_get($variable, 'name');

                $contactField = ContactField::query()
                    ->create([
                        'name' => $name,
                        'internal_name' => str_replace(' ', '_', strtolower($name)),
                        'type' => ContactFieldType::TEXT,
                    ]);

                $variable['contact_field_id'] = $contactField->id;
            }

            return $variable;
        });

        $templateWithName = Template::query()
            ->where('name', $name)
            ->where('id', '!=', $template->id)
            ->exists();

        if ($templateWithName) {
            throw new HttpResponseException(response()->json([
                'message' => 'Template name already exists',
                'message_code' => 'templater_name_already_exists',
                'errors' => ['name' => ['Template name already exists']],
            ], 422));
        }

        $language = (new TemplateLanguageService)->getCachedLanguages()
            ->where('id', $languageId)
            ->first();

        $template->update([
            'name' => $name,
            'language' => $language->code,
            'allow_category_change' => $allowCategoryChange,
            'category' => strtoupper($category),
            'body' => $body['text'],
            'body_example_variables' => json_encode($variables->toArray()),
            'footer' => $footer,
            'header' => json_encode($header ?? []),
            'buttons' => json_encode($buttons),
            'status' => 'PENDING',
        ]);

        return TemplateResource::make($template);
    }
}
