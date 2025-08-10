<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BusinessResource;
use App\Models\Business;
use App\Services\MetaService;

class BusinessesController extends Controller
{
    protected MetaService $metaService;

    public function __construct(MetaService $metaService)
    {
        $this->metaService = $metaService;
    }

    public function fetchAndStore()
    {
        $tenant = tenancy()->tenant;

        $token = $tenant->long_lived_access_token;

        if (! $token) {
            return response()->json([
                'error' => 'No long-lived access token found. Please authenticate with Meta first.',
            ], 401);
        }

        $businesses = $this->metaService->getBusinesses($token);

        if (empty($businesses)) {
            return response()->json([
                'message' => 'No businesses found or error fetching businesses from Meta API.',
            ], 404);
        }

        $storedBusinesses = [];

        foreach ($businesses as $business) {
            $storedBusiness = Business::updateOrCreate(
                ['meta_business_id' => $business['id']],
                [
                    'name' => $business['name'],
                ]
            );

            $storedBusinesses[] = $storedBusiness;
        }

        return response()->json([
            'message' => 'Businesses fetched and stored successfully.',
            'count' => count($storedBusinesses),
            'businesses' => $storedBusinesses,
        ], 200);
    }

    public function index()
    {
        $businesses = Business::all();

        return BusinessResource::collection($businesses);
    }
}
