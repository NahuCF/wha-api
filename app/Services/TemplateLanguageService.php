<?php

namespace App\Services;

use App\Models\TemplateLanguage;
use Illuminate\Support\Facades\Cache;

class TemplateLanguageService
{
    public function getCachedLanguages()
    {
        $cachedLanguages = Cache::remember(
            (new TemplateLanguage)->getTable(),
            now()->addDay(1),
            fn () => TemplateLanguage::on('landlord')->get()
        );

        return $cachedLanguages;
    }
}
