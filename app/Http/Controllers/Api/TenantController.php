<?php

namespace App\Http\Controllers\Api;

use App\Helpers\AppEnvironment;
use App\Http\Controllers\Controller;
use App\Http\Resources\BusinessResource;
use App\Http\Resources\TenantResource;
use App\Models\Business;
use App\Models\User;
use App\Models\Waba;
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
            : [['name' => 'Test Business', 'id' => rand(1, 10000)]];

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

            $wabas = AppEnvironment::isProduction()
                ? (new MetaService)->getWabasForBusiness($business['id'], $longLivedAccessToken)
                : [
                    [
                        'id' => rand(100000, 999999),
                        'name' => 'Test WABA 1',
                        'currency' => 'USD',
                        'timezone_id' => '1',
                        'message_template_namespace' => 'test_namespace_1',
                    ],
                    [
                        'id' => rand(100000, 999999),
                        'name' => 'Test WABA 2',
                        'currency' => 'EUR',
                        'timezone_id' => '2',
                        'message_template_namespace' => 'test_namespace_2',
                    ],
                ];

            $storedBusiness->wabas()->delete();

            foreach ($wabas as $waba) {
                Waba::query()->create([
                    'id' => Str::ulid(),
                    'business_id' => $storedBusiness->id,
                    'meta_waba_id' => $waba['id'],
                    'name' => $waba['name'],
                    'currency' => $waba['currency'] ?? null,
                    'timezone_id' => $waba['timezone_id'] ?? null,
                    'message_template_namespace' => $waba['message_template_namespace'] ?? null,
                ]);
            }

            $storedBusinesses[] = $storedBusiness;
        }

        $storedBusinesses = collect($storedBusinesses)->load('wabas');

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
