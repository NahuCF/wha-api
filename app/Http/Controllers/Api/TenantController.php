<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MetaService;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    public function storeAccessToken(Request $request)
    {
        $input = $request->validate([
            'access_token' => ['required', 'string'],
            'expires_in' => ['required', 'integer'],
        ]);

        $accessToken = data_get($input, 'access_token');
        $expiresIn = data_get($input, 'expires_in');

        $metaService = (new MetaService)->requestLongLivedToken($accessToken);

        return response()->json($metaService, 200);
    }
}
