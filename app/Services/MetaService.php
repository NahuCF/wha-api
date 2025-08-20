<?php

namespace App\Services;

use App\Enums\TemplateCategory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaService
{
    private $apiUrl;

    public function __construct()
    {
        $this->apiUrl = config('services.meta.api_url');
    }

    public function getToken()
    {
        return tenant('long_lived_access_token');
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

    public function getWabaID()
    {
        // Get the WABA ID from the authenticated user's selected WABA
        $user = Auth::user();

        if ($user && $user->waba) {
            return $user->waba->meta_waba_id;
        }

        // Fallback to tenant's default WABA
        $tenant = tenancy()->tenant;
        if ($tenant && $tenant->defaultWaba) {
            return $tenant->defaultWaba->meta_waba_id;
        }

        // Final fallback to business meta_business_id if no WABA is selected
        if ($user && $user->business) {
            return $user->business->meta_business_id;
        }

        return null;
    }

    public function getBusinesses(string $token)
    {
        try {
            $response = Http::withToken($token)
                ->get($this->buildUrl('me/businesses'), [
                    'fields' => 'id,name',
                ]);

            if ($response->successful()) {
                return $response->json('data', []);
            }

            return [];
        } catch (\Throwable $e) {
            Log::error('Error fetching businesses from Meta API: '.$e->getMessage());

            return [];
        }
    }

    public function getWabasForBusiness(string $businessId, string $token)
    {
        try {
            $response = Http::withToken($token)
                ->get($this->buildUrl("{$businessId}/owned_whatsapp_business_accounts"));

            if ($response->successful()) {
                return $response->json('data', []);
            }

            return [];
        } catch (\Throwable $e) {
            Log::error('Error fetching WABAs from Meta API: '.$e->getMessage());

            return [];
        }
    }

    public function createTemplate(string $name, TemplateCategory $category, string $language, array $components)
    {
        $payload = [
            'name' => $name,
            'language' => $language,
            'category' => $category,
            'components' => $components,
        ];

        try {
            $url = "{$this->getWabaID()}/message_templates";
            $response = Http::withToken($this->getToken())
                ->post($this->buildUrl($url), $payload);

            return $response->json();
        } catch (\Throwable $e) {
            Log::error('WhatsApp template creation failed: '.$e->getMessage());

            return ['error' => 'Failed to create template'];
        }
    }
}
