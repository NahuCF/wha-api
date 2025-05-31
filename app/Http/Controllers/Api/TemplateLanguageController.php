<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LanguageResource;
use App\Services\TemplateLanguageService;

class TemplateLanguageController extends Controller
{
    public function index()
    {
        $languages = (new TemplateLanguageService)->getCachedLanguages();

        return LanguageResource::collection($languages);
    }
}
