<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\WabaResource;
use App\Models\Business;
use Illuminate\Http\Request;

class WabaController extends Controller
{
    public function index(Request $request)
    {
        $input = $request->validate([
            'business_id' => ['required', 'ulid', 'exists:businesses,id'],
        ]);

        $businessId = data_get($input, 'business_id');

        $business = Business::find($businessId);

        return WabaResource::collection($business->wabas);
    }
}
