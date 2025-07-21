<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MetaService;

class MetaController extends Controller
{
    public function getAppId()
    {
        return [
            'app_id' => (new MetaService)->getAppId(),
        ];
    }
}
