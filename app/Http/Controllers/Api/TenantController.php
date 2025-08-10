<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Services\MetaService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TenantController extends Controller
{
    public function finishSetup(Request $request)
    {
        $input = $request->validate([
            'access_token' => ['required', 'string'],
        ]);

        $accessToken = data_get($input, 'access_token');

        $tenant = tenancy()->tenant;

        $metaService = (new MetaService)->requestLongLivedToken($accessToken);

        $longLivedAccessToken = $metaService['access_token'];
        $expiresIn = $metaService['expires_in'];

        $tenant->update([
            'long_lived_access_token' => $longLivedAccessToken,
            'long_lived_access_token_expires_at' => now()->addSeconds($expiresIn),
        ]);

        $businesses = (new MetaService)->getBusinesses($longLivedAccessToken);

        $tenant->businesses()->delete();

        $storedBusinesses = [];

        foreach ($businesses as $business) {
            $storedBusiness = Business::query()
                ->create([
                    'id' => Str::ulid(),
                    'tenant_id' => $tenant->id,
                    'meta_business_id' => $business['id'],
                    'name' => $business['name'],
                ]);

            $storedBusinesses[] = $storedBusiness;
        }

        return response()->json([
            'buss' => $storedBusinesses,
        ], 200);
    }
}
