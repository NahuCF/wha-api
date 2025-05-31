<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\HeaderComponentTypeResource;
use App\Services\TemplateHeaderTypeService;

class TemplateHeaderTypeController extends Controller
{
    public function index()
    {
        $cachedComponentTypes = (new TemplateHeaderTypeService)->getCachedHeaderTypes();

        return HeaderComponentTypeResource::collection($cachedComponentTypes);
    }
}
