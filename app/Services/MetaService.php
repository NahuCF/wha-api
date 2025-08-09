<?php

namespace App\Services;

use App\Enums\TemplateCategory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaService
{
    private $apiUrl;

    public function __construct()
    {
        $this->apiUrl = config('services.meta.api_url');
    }

    private function buildUrl($endpoint)
    {
        return $this->apiUrl.$endpoint;
    }

    public function getAppId()
    {
        return config('services.meta.app_id');
    }

    public function requestLongLivedToken($token)
    {
        $response = Http::get($this->buildUrl('oauth/access_token'), [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $this->getAppId(),
            'client_secret' => config('services.meta.secret'),
            'fb_exchange_token' => $token,
        ]);

        return [
            'access_token' => $response['access_token'],
            'expires_in' => $response['expires_in'],
        ];
    }

    public function getWabaID() {}

    public function createTemplate(string $name, TemplateCategory $category, string $language, array $components, string $token) 
    {
        $payload = [
            'name' => $name,
            'language' => $language,
            'category' => $category,
            'components' => $components,
        ];

        try {
            $url = "{$this->getWabaID()}/message_templates";
            $response = Http::withToken($token)
                ->post($this->buildUrl($url), $payload);

            return $response;

        } catch (\Throwable $e) {
            Log::error('WhatsApp template creation failed: '.$e->getMessage());

            return ['error' => 'Failed to create template'];
        }
    }
}
