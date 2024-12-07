<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CurrencyResource;
use App\Models\Currency;
use Illuminate\Support\Facades\Cache;

class CurrencyController extends Controller
{
    public function index()
    {
        $currencies = Cache::remember('currencies', 60, fn () => Currency::all());

        return CurrencyResource::collection($currencies);
    }
}
