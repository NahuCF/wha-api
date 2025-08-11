<?php

namespace App\Http\Controllers\Api;

use App\Helpers\AppEnvironment;
use App\Http\Controllers\Controller;
use App\Http\Resources\BusinessResource;
use App\Http\Resources\TenantResource;
use App\Models\Business;
use App\Models\User;
use App\Services\MetaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class TenantController extends Controller
{
    public function metaAccess(Request $request)
    {
        $input = $request->validate([
            'access_token' => ['required', 'string'],
        ]);

        $accessToken = data_get($input, 'access_token');

        $tenant = tenancy()->tenant;

        $metaService = AppEnvironment::isProduction()
            ? (new MetaService)->requestLongLivedToken($accessToken)
            : ['access_token' => $accessToken, 'expires_in' => 1000000000];

        $longLivedAccessToken = $metaService['access_token'];
        $expiresIn = $metaService['expires_in'];

        $tenant->update([
            'long_lived_access_token' => $longLivedAccessToken,
            'long_lived_access_token_expires_at' => now()->addSeconds($expiresIn),
        ]);

        $businesses = AppEnvironment::isProduction()
            ? (new MetaService)->getBusinesses($longLivedAccessToken)
            : [['name' => 'Test Business', 'id' => '123456789']];

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

        return BusinessResource::collection($storedBusinesses);
    }

    public function completeProfile(Request $request)
    {
        $input = $request->validate([
            'business_id' => ['ulid', 'exists:businesses,id'],
        ]);

        $businessId = data_get($input, 'business_id');

        $user = User::find(Auth::user()->id);
        $tenant = tenancy()->tenant;

        $user->update([
            'business_id' => $businessId,
        ]);

        $tenant->update([
            'is_profile_completed' => true,
        ]);

        return TenantResource::make($tenant);
    }
}
