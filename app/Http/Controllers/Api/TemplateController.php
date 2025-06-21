<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTemplateRequest;
use App\Http\Resources\TemplateResource;
use App\Models\Template;
use App\Services\TemplateLanguageService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TemplateController extends Controller
{
    public function index(Request $request)
    {
        $input = $request->validate([
            'rows_per_page' => ['sometimes', 'integer'],
            'status' => ['sometimes', 'string'],
        ]);

        $status = data_get($input, 'status');
        $rowsPerPage = data_get($input, 'rows_per_page', 10);

        $templates = Template::query()
            ->when($status, fn ($q) => $q->where('status', $status))
            ->paginate($rowsPerPage);

        return TemplateResource::collection($templates);
    }

    public function store(StoreTemplateRequest $request)
    {
        $input = $request->validated();

        $name = data_get($input, 'name');
        $languageId = data_get($input, 'language_id');
        $category = data_get($input, 'category');
        $header = data_get($input, 'components.header');
        $body = data_get($input, 'components.body');
        $footer = data_get($input, 'components.footer', '');
        $buttons = data_get($input, 'components.buttons', []);

        if (Template::query()->where('name', $name)->exists()) {
            throw ValidationException::withMessages([
                'name' => 'Template name already exists',
            ]);
        }

        $language = (new TemplateLanguageService)->getCachedLanguages()
            ->where('id', $languageId)
            ->first();

        $template = Template::create([
            'name' => $name,
            'language' => $language->code,
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
