<?php

namespace App\Services;

class MetaService
{
    private $httpService;

    public function __construct()
    {
        $this->httpService = new HttpService(config('services.meta.api_url'));
    }

    public function requestLongLivedToken($token)
    {
        $response = $this->httpService->get('oauth/access_token', [
            'grant_type' => 'fb_exchange_token',
            'client_id' => config('services.meta.app_id'),
            'client_secret' => config('services.meta.secret'),
            'fb_exchange_token' => $token,
        ]);

        return [
            'token' => $response['access_token'],
            'expires_in' => $response['expires_in'],
        ];
    }
}
