<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\KnownPlaceResource;
use App\Models\KnownPlace;
use Illuminate\Support\Facades\Cache;

class KnownPlaceController extends Controller
{
    public function index()
    {
        $data = Cache::remember('kown_places', 60, fn () => KnownPlace::all());

        return KnownPlaceResource::collection($data);
    }
}
