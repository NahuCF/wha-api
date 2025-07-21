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
            'access_token' => ['required', 'string']
        ]);

        $accessToken = data_get($input, 'access_token');

        $metaService = (new MetaService)->requestLongLivedToken($accessToken);

        return response()->json($metaService, 200);
    }
}
