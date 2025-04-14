<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TemplateController extends Controller
{
    public function store(Request $request)
    {
        $input = $request->validate([
            'name' => ['required', 'string', 'max:512'],
            'body' => ['required', 'string', 'max:1024'],
            'footer' => ['sometimes', 'string', 'max:60'],
            'language_id' => ['required', 'integer'],
            'template_category_id' => ['required', 'integer'],
        ]);

        $name = data_get($input, 'name');
        $body = data_get($input, 'body');
        $footer = data_get($input, 'footer', '');
        $languageId = data_get($input, 'language_id');
        $templateCategoryId = data_get($input, 'template_category_id');

        $templateWithName = Template::query()
            ->where('name', $name)
            ->first();

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
                'body' => $body,
                'footer' => $footer,
                'category' => strtoupper($category->name),
                'language' => $language->code,
            ]);

        return response()->json($template);
    }
}
