<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TemplateCategoryResource;
use App\Models\TemplateCategory;
use Illuminate\Support\Facades\Cache;

class TemplateCategoryController extends Controller
{
    public function index()
    {
        $cachedCategories = Cache::remember('template_categories', now()->addDay(1), fn () => TemplateCategory::all());

        return TemplateCategoryResource::collection($cachedCategories);
    }
}
