<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LanguageResource;
use App\Models\Language;
use Illuminate\Support\Facades\Cache;

class LanguageController extends Controller
{
    public function index()
    {
        $cachedLanguages = Cache::remember('languages', now()->addDay(1), fn () => Language::all());

        return LanguageResource::collection($cachedLanguages);
    }
}
