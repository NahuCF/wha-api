<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Services\MetaService;
use App\Http\Controllers\Controller;

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

        $metaService = (new MetaService())->requestLongLivedToken($accessToken);

        return response()->json($metaService, 200);
    }
}
