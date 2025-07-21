<?php

namespace App\Services;

use App\Enums\TemplateCategory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class MetaService
{
    private $httpService;

    public function __construct()
    {
        $this->httpService = new HttpService(config('services.meta.api_url'));
    }

    public function getAppId()
    {
        return config('services.meta.app_id');
    }

    public function requestLongLivedToken($token)
    {
        $response = Http::get('https://graph.facebook.com/v22.0/oauth/access_token', [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $this->getAppId(),
            'client_secret' => config('services.meta.secret'),
            'fb_exchange_token' => $token,
        ]);

        return [
            'token' => $response['access_token'],
            'expires_in' => $response['expires_in'],
        ];
    }

    public function createTemplate(string $name, TemplateCategory $category, string $language, array $components, string $token): array
    {
        $payload = [
            'name' => $name,
            'language' => $language,
            'category' => $category,
            'components' => $components,
        ];

        try {
            $response = $this->httpService->post('templates', $payload);

            return $response;

        } catch (\Throwable $e) {
            Log::error('WhatsApp template creation failed: '.$e->getMessage());

            return ['error' => 'Failed to create template'];
        }
    }
}
