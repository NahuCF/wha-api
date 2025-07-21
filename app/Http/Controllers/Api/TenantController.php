<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MetaService;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    public function storeLongLivedToken(Request $request)
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

        return response()->json($tenant, 200);
    }
}
