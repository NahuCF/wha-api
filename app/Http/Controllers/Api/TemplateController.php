<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTemplateRequest;
use App\Http\Resources\TemplateResource;
use App\Models\Template;
use App\Models\TemplateButton;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
        $templateCategoryId = data_get($input, 'template_category_id');
        $header = data_get($input, 'components.header');
        $body = data_get($input, 'components.body');
        $footer = data_get($input, 'components.footer', '');
        $buttons = collect(data_get($input, 'components.buttons', []));

        $templateWithName = Template::query()
            ->where('name', $name)
            ->exists();

        if ($templateWithName) {
            throw ValidationException::withMessages([
                'name' => 'Template name already exists',
            ]);
        }

        $category = Cache::remember(
            'template_category_id:'.$templateCategoryId,
            now()->addDay(1),
            function () use ($templateCategoryId) {
                return DB::connection('landlord')
                    ->table('template_categories')
                    ->where('id', $templateCategoryId)
                    ->first();
            });

        $language = Cache::remember(
            'language_id:'.$languageId,
            now()->addDay(1),
            function () use ($languageId) {
                return DB::connection('landlord')
                    ->table('languages')
                    ->where('id', $languageId)
                    ->first();
            }
        );

        $template = Template::query()
            ->create([
                'name' => $name,
                'body' => $body['text'],
                'footer' => $footer,
                'category' => strtoupper($category->name),
                'language' => $language->code,
                'header_type' => $header['type'],
                'header_text' => $header['text'],
                'status' => 'PENDING',
            ]);

        if ($buttons) {
            $buttonsData = $buttons->map(function ($button) use ($template) {
                return [
                    'type' => $button['type'],
                    'text' => $button['text'],
                    'url' => data_get($button, 'url'),
                    'phone_prefix' => data_get($button, 'phone_prefix'),
                    'phone_number' => data_get($button, 'phone_number'),
                    'index' => data_get($button, 'index', 0),
                    'template_id' => $template->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            });

            TemplateButton::insert($buttonsData->toArray());
        }

        return response()->json($template);
    }
}
