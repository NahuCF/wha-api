<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\HeaderComponentTypeResource;
use App\Models\HeaderComponentType;
use Illuminate\Support\Facades\Cache;

class ComponentTypeController extends Controller
{
    public function headerTypes()
    {
        $cachedComponentTypes = Cache::remember('header_component_types', now()->addDay(1), fn () => HeaderComponentType::all());

        return HeaderComponentTypeResource::collection($cachedComponentTypes);
    }
}
