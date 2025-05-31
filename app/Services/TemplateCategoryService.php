<?php

namespace App\Services;

use App\Models\TemplateCategory;
use Illuminate\Support\Facades\Cache;

class TemplateCategoryService
{
    public function getCachedCategories()
    {
        $cachedCategories = Cache::remember(
            'template_categories',
            now()->addDay(1),
            fn () => TemplateCategory::on('landlord')->get()
        );

        return $cachedCategories;
    }
}
