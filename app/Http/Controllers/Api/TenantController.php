<?php

namespace App\Http\Controllers\Api;

use App\Enums\CodeVerificationStatus;
use App\Helpers\AppEnvironment;
use App\Http\Controllers\Controller;
use App\Http\Resources\BusinessResource;
use App\Http\Resources\PhoneNumberResource;
use App\Models\Business;
use App\Models\PhoneNumber;
use App\Models\User;
use App\Models\Waba;
use App\Services\MetaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class TenantController extends Controller
{
    /**
     * Stores tenant token and businesses with wabas
     *
     * Returns businesses
     */
    public function metaAccess(Request $request)
    {
        $input = $request->validate([
            'access_token' => ['required', 'string'],
        ]);

        $accessToken = data_get($input, 'access_token');

        $tenant = tenancy()->tenant;
        $user = Auth::user();

        $metaService = AppEnvironment::isProduction()
            ? (new MetaService)->requestLongLivedToken($accessToken)
            : ['access_token' => $accessToken, 'expires_in' => 1000000000];

        $longLivedAccessToken = $metaService['access_token'];
        $expiresIn = $metaService['expires_in'];

        $tenant->update([
            'long_lived_access_token' => $longLivedAccessToken,
            'long_lived_access_token_expires_at' => now()->addSeconds($expiresIn),
        ]);

        $businesses = AppEnvironment::isProduction()
            ? (new MetaService)->getBusinesses($longLivedAccessToken)
            : [['name' => 'Test Business', 'id' => rand(1, 10000)]];

        $tenant->businesses()->delete();

        $storedBusinesses = [];

        foreach ($businesses as $business) {
            $storedBusiness = Business::query()
                ->create([
                    'id' => Str::ulid(),
                    'tenant_id' => $tenant->id,
                    'meta_business_id' => $business['id'],
                    'name' => $business['name'],
                ]);

            $wabas = AppEnvironment::isProduction()
                ? (new MetaService)->getWabasForBusiness($business['id'], $longLivedAccessToken)
                : [
                    [
                        'id' => rand(100000, 999999),
                        'name' => 'Test WABA 1',
                        'currency' => 'USD',
                        'timezone_id' => '1',
                        'message_template_namespace' => 'test_namespace_1',
                    ],
                    [
                        'id' => rand(100000, 999999),
                        'name' => 'Test WABA 2',
                        'currency' => 'EUR',
                        'timezone_id' => '2',
                        'message_template_namespace' => 'test_namespace_2',
                    ],
                ];

            $storedBusiness->wabas()->delete();

            foreach ($wabas as $waba) {
                Waba::query()->create([
                    'id' => Str::ulid(),
                    'business_id' => $storedBusiness->id,
                    'meta_waba_id' => $waba['id'],
                    'name' => $waba['name'],
                    'currency' => $waba['currency'] ?? null,
                    'timezone_id' => $waba['timezone_id'] ?? null,
                    'message_template_namespace' => $waba['message_template_namespace'] ?? null,
                ]);
            }

            $storedBusinesses[] = $storedBusiness;
        }

        foreach ($storedBusinesses as $business) {
            $business->load('wabas');
        }

        $allWabaIds = Waba::query()
            ->whereHas('business', fn ($q) => $q->where('tenant_id', $tenant->id))
            ->pluck('id')
            ->toArray();

        $user->wabas()->sync($allWabaIds);

        return BusinessResource::collection($storedBusinesses);
    }

    /**
     * Stores default waba and phone numbers of waba
     *
     * Return phone numbers
     */
    public function storeDefaultWaba(Request $request)
    {
        $input = $request->validate([
            'business_id' => ['required', 'ulid', 'exists:businesses,id'],
            'waba_id' => ['required', 'ulid', 'exists:wabas,id'],
        ]);

        $businessId = data_get($input, 'business_id');
        $wabaId = data_get($input, 'waba_id');

        $user = User::find(Auth::user()->id);

        $user->update([
            'business_id' => $businessId,
            'default_waba_id' => $wabaId,
        ]);

        $waba = Waba::find($wabaId);

        $phoneNumbers = AppEnvironment::isProduction()
            ? (new MetaService)->getPhoneNumbers($waba->meta_waba_id)
            : [
                [
                    'id' => Str::ulid(),
                    'display_phone_number' => '1234567890',
                    'verified_name' => 'Test Business',
                    'quality_rating' => 'GREEN',
                    'code_verification_status' => CodeVerificationStatus::VERIFIED->value,
                    'status' => 'CONNECTED',
                ],
                [
                    'id' => Str::ulid(),
                    'display_phone_number' => '0987654321',
                    'verified_name' => 'Test Business 2',
                    'quality_rating' => 'YELLOW',
                    'code_verification_status' => CodeVerificationStatus::NOT_VERIFIED->value,
                    'status' => 'PENDING',
                ],
            ];

        $waba->phoneNumbers()->delete();

        $storedPhoneNumbers = [];
        foreach ($phoneNumbers as $phoneNumber) {
            $stored = PhoneNumber::create([
                'waba_id' => $wabaId,
                'meta_id' => data_get($phoneNumber, 'id'),
                'display_phone_number' => data_get($phoneNumber, 'display_phone_number'),
                'verified_name' => data_get($phoneNumber, 'verified_name'),
                'quality_rating' => data_get($phoneNumber, 'quality_rating'),
                'code_verification_status' => data_get($phoneNumber, 'code_verification_status', 'NOT_VERIFIED'),
                'status' => data_get($phoneNumber, 'status'),
                'pin' => null,
                'is_registered' => data_get($phoneNumber, 'code_verification_status') === 'VERIFIED',
            ]);
            $storedPhoneNumbers[] = $stored;
        }

        // Only return verified phone numbers
        $verifiedPhoneNumbers = collect($storedPhoneNumbers)->filter(function ($phoneNumber) {
            return $phoneNumber->code_verification_status === CodeVerificationStatus::VERIFIED;
        });

        return PhoneNumberResource::collection($verifiedPhoneNumbers);
    }

    /**
     * Set a default number for the user or request verification code for a number
     *
     * Return a phone number
     */
    public function selectNumber(Request $request)
    {
        $input = $request->validate([
            'phone_id' => ['required_without_all:display_phone_number,verified_name,cc', 'ulid', 'exists:phone_numbers,id'],
            'display_phone_number' => ['required_with:verified_name,cc', 'string'],
            'verified_name' => ['required_with:display_phone_number,cc', 'string'],
            'cc' => ['required_with:display_phone_number,verified_name', 'string'],
        ]);

        $user = Auth::user();
        $tenant = tenancy()->tenant;
        $phoneId = data_get($input, 'phone_id');

        if ($phoneId) {
            $phoneNumber = PhoneNumber::query()
                ->where('id', $phoneId)
                ->whereHas('waba', function ($query) use ($user) {
                    $query->where('id', $user->default_waba_id);
                })
                ->firstOrFail();

            $user->update(['default_phone_id' => $phoneId]);

            return PhoneNumberResource::make($phoneNumber);
        }

        $displayPhoneNumber = data_get($input, 'display_phone_number');
        $verifiedName = data_get($input, 'verified_name');
        $countryCode = data_get($input, 'cc');

        $waba = Waba::find($user->default_waba_id);

        $metaPhoneNumber = AppEnvironment::isProduction()
            ? (new MetaService)->addPhoneNumber($waba->meta_waba_id, $displayPhoneNumber, $countryCode, $verifiedName)
            : ['id' => Str::ulid()];

        $error = data_get($metaPhoneNumber, 'error');
        if ($error) {
            return response()->json($this->metaErrorResponse($error), 400);
        }

        $metaPhoneNumberId = data_get($metaPhoneNumber, 'id');

        $phoneNumber = PhoneNumber::create([
            'waba_id' => $waba->id,
            'meta_id' => $metaPhoneNumberId,
            'display_phone_number' => $displayPhoneNumber,
            'verified_name' => $verifiedName,
            'quality_rating' => 'UNKNOWN',
            'code_verification_status' => CodeVerificationStatus::NOT_VERIFIED->value,
            'status' => null,
            'pin' => null,
            'is_registered' => false,
        ]);

        $verificationResult = AppEnvironment::isProduction()
            ? (new MetaService)->requestPhoneVerification($metaPhoneNumberId, 'SMS', 'en_US')
            : ['success' => true];

        $error = data_get($verificationResult, 'error');
        if ($error) {
            return response()->json($this->metaErrorResponse($error), 400);
        }

        if (AppEnvironment::isLocal()) {
            sleep(5);
        }

        $tenant->update(['is_profile_completed' => true]);

        return PhoneNumberResource::make($phoneNumber);
    }

    /**
     * Verify a phone number and returns
     *
     * Return a phone number
     */
    public function verifyNumberCode(Request $request)
    {
        $input = $request->validate([
            'phone_id' => ['required', 'ulid', 'exists:phone_numbers,id'],
            'code' => ['required', 'string'],
        ]);

        $phoneId = data_get($input, 'phone_id');
        $code = data_get($input, 'code');

        $user = Auth::user();
        $tenant = tenancy()->tenant;

        $phoneNumber = PhoneNumber::query()
            ->where('id', $phoneId)
            ->whereHas('waba', function ($query) use ($user) {
                $query->where('id', $user->default_waba_id);
            })
            ->firstOrFail();

        $result = AppEnvironment::isProduction()
            ? (new MetaService)->verifyPhoneNumber($phoneNumber->meta_id, $code)
            : ['success' => true];

        $error = data_get($result, 'error');
        if ($error) {
            return response()->json($this->metaErrorResponse($error), 400);
        }

        $user->update(['default_phone_id' => $phoneId]);

        $pin = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $updateData['pin'] = $pin;

        $phoneNumber->update($updateData);

        if (! $phoneNumber->is_registered) {
            $registerResult = AppEnvironment::isProduction()
                ? (new MetaService)->registerPhoneNumber($phoneNumber->meta_id, $pin)
                : ['success' => true];

            if (isset($registerResult['success']) && $registerResult['success']) {
                $phoneNumber->update([
                    'is_registered' => true,
                    'code_verification_status' => CodeVerificationStatus::VERIFIED->value,
                    'status' => 'CONNECTED',
                ]);
            }
        }

        $tenant->update(['is_profile_completed' => true]);

        return PhoneNumberResource::make($phoneNumber->fresh());
    }

    /**
     * Disconnect/delete a phone number from WhatsApp Business
     */
    public function disconnectPhoneNumber(Request $request)
    {
        $input = $request->validate([
            'phone_id' => ['required', 'ulid', 'exists:phone_numbers,id'],
        ]);

        $phoneId = data_get($input, 'phone_id');
        $user = User::find(Auth::user()->id);

        $phoneNumber = PhoneNumber::query()
            ->where('id', $phoneId)
            ->whereHas('waba', function ($query) use ($user) {
                $query->where('id', $user->default_waba_id);
            })
            ->firstOrFail();

        $result = AppEnvironment::isProduction()
            ? (new MetaService)->deletePhoneNumber($phoneNumber->meta_id)
            : ['success' => true];

        $error = data_get($result, 'error');
        if ($error) {
            return response()->json($this->metaErrorResponse($error), 400);
        }

        if ($user->default_phone_id === $phoneId) {
            $user->update(['default_phone_id' => null]);
        }

        $phoneNumber->delete();

        return response()->json([
            'message' => 'Phone number disconnected successfully',
        ]);
    }

    private function metaErrorResponse($error)
    {
        $errorMessage = data_get($error, 'error_user_msg');
        $errorTitle = data_get($error, 'error_user_title');

        return [
            'message' => $errorTitle,
            'error' => $errorMessage,
        ];
    }
}
