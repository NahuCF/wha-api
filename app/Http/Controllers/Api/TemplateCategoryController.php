<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TemplateCategoryResource;
use App\Services\TemplateCategoryService;

class TemplateCategoryController extends Controller
{
    public function index()
    {
        $cachedCategories = (new TemplateCategoryService)->getCachedCategories();

        return TemplateCategoryResource::collection($cachedCategories);
    }
}
