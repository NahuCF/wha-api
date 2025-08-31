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
        $user = Auth::user();

        return $user->defaultWaba->meta_waba_id;
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

    public function updateTemplate(string $templateId, array $components)
    {
        $payload = [
            'components' => $components,
        ];

        try {
            $url = "{$this->getWabaID()}/{$templateId}";
            $response = Http::withToken($this->getToken())
                ->post($this->buildUrl($url), $payload);

            return $response->json();
        } catch (\Throwable $e) {
            Log::error('WhatsApp template update failed: '.$e->getMessage());

            return ['error' => 'Failed to update template'];
        }
    }

    public function getPhoneNumbers(string $wabaId)
    {
        try {
            $response = Http::withToken($this->getToken())
                ->get($this->buildUrl("{$wabaId}/phone_numbers"), [
                    'fields' => 'id,display_phone_number,verified_name,code_verification_status,quality_rating,status,name_status,is_official_business_account,account_mode,certificate',
                ]);

            if ($response->successful()) {
                return $response->json('data', []);
            }

            return [];
        } catch (\Throwable $e) {
            Log::error('Error fetching phone numbers from Meta API: '.$e->getMessage());

            return [];
        }
    }

    public function addPhoneNumber(string $wabaId, string $phoneNumber, string $countryCode, string $verifiedName)
    {
        try {
            $response = Http::withToken($this->getToken())
                ->post($this->buildUrl("{$wabaId}/phone_numbers"), [
                    'cc' => $countryCode,
                    'phone_number' => $phoneNumber,
                    'verified_name' => $verifiedName,
                ]);

            return $response->json();
        } catch (\Throwable $e) {
            Log::error('Error adding phone number to Meta API: '.$e->getMessage());

            return ['error' => 'Failed to add phone number', 'exception' => $e->getMessage()];
        }
    }

    public function requestPhoneVerification(string $phoneId, string $codeMethod = 'SMS', string $language = 'en_US')
    {
        try {
            $response = Http::withToken($this->getToken())
                ->post($this->buildUrl("{$phoneId}/request_code"), [
                    'code_method' => $codeMethod,
                    'language' => $language,
                ]);

            return $response->json();
        } catch (\Throwable $e) {
            Log::error('Error requesting verification code from Meta API: '.$e->getMessage());

            return ['error' => 'Failed to request verification code', 'exception' => $e->getMessage()];
        }
    }

    public function verifyPhoneNumber(string $phoneId, string $code)
    {
        try {
            $response = Http::withToken($this->getToken())
                ->post($this->buildUrl("{$phoneId}/verify_code"), [
                    'code' => $code,
                ]);

            return $response->json();
        } catch (\Throwable $e) {
            Log::error('Error verifying phone number with Meta API: '.$e->getMessage());

            return ['error' => 'Failed to verify phone number', 'exception' => $e->getMessage()];
        }
    }

    public function deletePhoneNumber(string $phoneId)
    {
        try {
            $response = Http::withToken($this->getToken())
                ->delete($this->buildUrl($phoneId));

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json()];
            }

            Log::error('Failed to delete phone number: '.$response->body());

            return ['error' => $response->json()];
        } catch (\Throwable $e) {
            Log::error('Error deleting phone number from Meta API: '.$e->getMessage());

            return ['error' => 'Failed to delete phone number', 'exception' => $e->getMessage()];
        }
    }

    public function registerPhoneNumber(string $phoneId, string $pin)
    {
        try {
            $response = Http::withToken($this->getToken())
                ->post($this->buildUrl("{$phoneId}/register"), [
                    'pin' => $pin,
                    'messaging_product' => 'whatsapp',
                ]);

            return $response->json();
        } catch (\Throwable $e) {
            Log::error('Error registering phone number with Meta API: '.$e->getMessage());

            return ['error' => 'Failed to register phone number', 'exception' => $e->getMessage()];
        }
    }
}
