<?php

namespace App\Services;

use App\Models\TemplateHeaderType;
use Illuminate\Support\Facades\Cache;

class TemplateHeaderTypeService
{
    public function getCachedHeaderTypes()
    {
        $cachedHeaderTypes = Cache::remember(
            'template_header_types',
            now()->addDay(1),
            fn () => TemplateHeaderType::all()
        );

        return $cachedHeaderTypes;
    }
}
