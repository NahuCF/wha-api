<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\IndustryResource;
use App\Models\Industry;
use Illuminate\Support\Facades\Cache;

class IndustryController extends Controller
{
    public function index()
    {
        $data = Cache::remember('industries', 60, fn () => Industry::all());

        return IndustryResource::collection($data);
    }
}
