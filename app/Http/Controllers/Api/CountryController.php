<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CountryResource;
use App\Models\Country;
use Illuminate\Support\Facades\Cache;

class CountryController extends Controller
{
    public function index()
    {
        $countries = Cache::remember('countries', 60, fn () => Country::all());

        return CountryResource::collection($countries);
    }
}
