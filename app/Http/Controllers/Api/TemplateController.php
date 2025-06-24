<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTemplateRequest;
use App\Http\Resources\TemplateResource;
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
            'body_example_variables' => json_encode($body['variables'] ?? []),
            'footer' => $footer,
            'header' => json_encode($header ?? []),
            'buttons' => json_encode($buttons),
            'status' => 'PENDING',
        ]);

        return TemplateResource::make($template);
    }
}
