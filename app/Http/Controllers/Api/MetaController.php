<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MetaService;
use Illuminate\Http\Request;

class MetaController extends Controller
{
    public function getAppId()
    {
        return [
            'app_id' => (new MetaService)->getAppId(),
        ];
    }

    public function handshake(Request $request)
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        $verifyToken = config('services.meta.webhook_verify_token');

        if ($mode === 'subscribe' && $token === $verifyToken) {
            return response($challenge, 200);
        }

        return response('Forbidden', 403);
    }

    public function callback(Request $request) {}
}
