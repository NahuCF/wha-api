<?php

namespace App\Http\Controllers\Api;

use App\Enums\SystemRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\TenantResource;
use App\Http\Resources\UserResource;
use App\Jobs\SendVerifyAccountEmail;
use App\Models\Tenant;
use App\Models\TenantSettings;
use App\Models\TenantVerificationEmail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\ClientRepository;
use Spatie\Permission\Models\Role;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $input = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'tenant_id' => ['sometimes', 'string'],
        ]);

        $email = data_get($input, 'email');
        $password = data_get($input, 'password');
        $tenantId = data_get($input, 'tenant_id');

        $tenantUsers = User::query()
            ->where('email', $email)
            ->with('tenant')
            ->get();

        if ($tenantUsers->isEmpty()) {
            throw ValidationException::withMessages([
                'credentials' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $tenantId && $tenantUsers->count() > 1) {
            $tenants = Tenant::query()
                ->with('settings')
                ->whereIn('id', $tenantUsers->pluck('tenant_id')->unique())
                ->get();

            return TenantResource::collection($tenants);
        }

        $tenantUser = $tenantUsers->first();

        $tenant = Tenant::with('settings')->find($tenantUser->tenant_id);

        $user = User::query()
            ->with('roles', 'defaultWaba')
            ->where('email', $email)
            ->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'credentials' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $tenant->is_verified_email) {
            return response()->json([
                'message' => 'Tenant is not verified yet, please wait for the email to be verified.',
                'message_code' => 'tenant_email_not_verified',
            ]);
        }

        $user->loadPermissionNames();

        $user->tokens()->delete();
        $token = $user->createToken('tenant-token')->accessToken;

        $user->update(['status' => UserStatus::ACTIVE->value]);

        $user->load('business', 'wabas');

        return TenantResource::make($tenant)->additional([
            'meta' => [
                'user' => UserResource::make($user),
                'token' => $token,
            ],
        ]);
    }

    public function register(Request $request)
    {
        $input = $request->validate([
            'name' => ['required', 'string'],
            'cellphone' => ['required', 'string'],
            'cellphone_prefix' => ['required', 'string'],
            'work_email' => ['required', 'email'],
            'company_name' => ['required', 'string'],
            'password' => ['required', 'string'],
            'language' => ['sometimes', 'string', 'in:en,es'],
        ]);

        $name = data_get($input, 'name');
        $cellphone = data_get($input, 'cellphone');
        $cellphonePrefix = data_get($input, 'cellphone_prefix');
        $workEmail = data_get($input, 'work_email');
        $password = data_get($input, 'password');
        $companyName = data_get($input, 'company_name');
        $language = data_get($input, 'language', 'en');

        $isEmailInUse = User::query()
            ->where('email', $workEmail)
            ->exists();

        if ($isEmailInUse) {
            return response()->json([
                'message' => 'Email already in use',
                'message_code' => 'email_in_use',
            ]);
        }

        $tenant = Tenant::create([
            'company_name' => $companyName,
        ]);

        TenantSettings::create([
            'tenant_id' => $tenant->id,
            'language' => $language,
        ]);

        $user = User::create([
            'id' => Str::ulid(),
            'tenant_id' => $tenant->id,
            'name' => $name,
            'email' => $workEmail,
            'cellphone_number' => $cellphone,
            'cellphone_prefix' => $cellphonePrefix,
            'password' => bcrypt($password),
        ]);

        $user->assignRole(SystemRole::OWNER);
        $user->givePermissionTo(Role::findByName(SystemRole::OWNER->value)->permissions);

        $client = new ClientRepository;

        $client->createPasswordGrantClient(null, 'Default password grant client', '');
        $client->createPersonalAccessClient(null, 'Default personal access client', '');

        $tenant->load('settings');

        return TenantResource::make($tenant);
    }

    public function sendVerifyAccount(Request $request)
    {
        $input = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = data_get($input, 'email');

        $user = User::query()
            ->where('email', $email)
            ->first();

        if (! $user) {
            return response()->json([
                'message' => 'User not found.',
                'message_code' => 'user_not_found',
            ], 422);
        }

        $tenant = Tenant::query()
            ->where('id', $user->tenant_id)
            ->where('is_verified_email', false)
            ->first();

        if (! $tenant) {
            return response()->json([
                'message' => 'Tenant not found.',
                'message_code' => 'tenant_not_found',
            ], 422);
        }

        $latestEmail = TenantVerificationEmail::query()
            ->where('tenant_id', $tenant->id)
            ->latest('id')
            ->first();

        // Prevent send too many emails
        if ($latestEmail && Carbon::parse($latestEmail->sent_at)->diffInSeconds(now()) < 60) {
            return response()->json([], 200);
        }

        $token = (string) Str::uuid();

        TenantVerificationEmail::query()
            ->create([
                'id' => (string) Str::ulid(),
                'tenant_id' => $tenant->id,
                'token' => $token,
                'sent_at' => now(),
            ]);

        $link = config('app.client_url').'/verify-account?token='.$token;

        $tenantSettings = TenantSettings::where('tenant_id', $tenant->id)->first();
        $locale = $tenantSettings->language ?? 'en';

        new SendVerifyAccountEmail(
            email: $user->email,
            link: $link,
            name: $user->name,
            locale: $locale
        );

        return response()->json([], 200);
    }

    public function storeBasicInformation(Request $request, Tenant $tenant)
    {
        $input = $request->validate([
            'country_id' => ['required', 'integer'],
            'currency_id' => ['required', 'integer'],
            'timezone_id' => ['required', 'integer'],
            'employees_amount' => ['required', 'integer'],
            'known_place_id' => ['required', 'integer'],
        ]);

        if ($tenant->filled_basic_information) {
            throw ValidationException::withMessages([
                'filled_basic_information' => 'Basic information already filled',
            ]);
        }

        $countryId = data_get($input, 'country_id');
        $currencyId = data_get($input, 'currency_id');
        $timezoneId = data_get($input, 'timezone_id');
        $employeesAmount = data_get($input, 'employees_amount');
        $knownUs = data_get($input, 'known_place_id');

        $tenant->update([
            'country_id' => $countryId,
            'currency_id' => $currencyId,
            'timezone_id' => $timezoneId,
            'employees_amount' => $employeesAmount,
            'known_place_id' => $knownUs,
            'filled_basic_information' => true,
        ]);

        return TenantResource::make($tenant);
    }

    public function logout(Request $request)
    {
        $request->user()->token()->revoke();

        return response()->noContent();
    }

    public function loginWithToken(Request $request)
    {
        $input = $request->validate([
            'token' => ['required', 'string'],
        ]);

        $token = data_get($input, 'token');

        $verificationRecord = TenantVerificationEmail::query()
            ->where('token', $token)
            ->first();

        if (! $verificationRecord) {
            return response()->json([
                'message' => 'Invalid or expired token.',
                'message_code' => 'invalid_token',
            ], 422);
        }

        if (Carbon::parse($verificationRecord->sent_at)->diffInHours(now()) > 24) {
            return response()->json([
                'message' => 'Invalid or expired token.',
                'message_code' => 'expired_token',
            ], 422);
        }

        $tenant = Tenant::with('settings')->find($verificationRecord->tenant_id);

        $user = User::query()
            ->with('roles', 'defaultWaba')
            ->where('tenant_id', $tenant->id)
            ->whereHas('roles', function ($query) {
                $query->where('name', SystemRole::OWNER->value);
            })
            ->first();

        if (! $tenant->verified_email) {
            $tenant->update([
                'verified_email' => true,
            ]);
        }

        $user->loadPermissionNames();

        $user->tokens()->delete();
        $accessToken = $user->createToken('tenant-token')->accessToken;

        $user->update(['status' => UserStatus::ACTIVE->value]);

        $user->load('business', 'wabas');

        $verificationRecord->delete();

        return TenantResource::make($tenant)->additional([
            'meta' => [
                'user' => UserResource::make($user),
                'token' => $accessToken,
            ],
        ]);
    }

    public function userTenants(Request $request)
    {
        $input = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = data_get($input, 'email');

        $tenantIds = User::query()
            ->where('email', $email)
            ->with('tenant')
            ->pluck('tenant_id')
            ->toArray();

        $tenants = Tenant::query()
            ->whereIn('id', $tenantIds)
            ->get();

        return TenantResource::collection($tenants);
    }
}
