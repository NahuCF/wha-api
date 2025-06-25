<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TenantResource;
use App\Http\Resources\UserResource;
use App\Jobs\SendOTPCode;
use App\Jobs\SendVerifyAccountEmail;
use App\Models\Tenant;
use App\Models\TenantOtp;
use App\Models\TenantVerificationEmail;
use App\Models\User;
use App\Services\JobDispatcherService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\ClientRepository;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $input = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $email = data_get($input, 'email');
        $password = data_get($input, 'password');

        $tenant = Tenant::query()
            ->where('email', $email)
            ->first();

        if (! $tenant) {
            throw ValidationException::withMessages([
                'credentials' => ['The provided credentials are incorrect.'],
            ]);
        }

        tenancy()->initialize($tenant);

        $user = User::query()
            ->where('email', $email)
            ->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'credentials' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('tenant-token')->accessToken;

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
            'work_email' => ['required', 'email', 'unique:tenants,email'],
            'password' => ['required', 'string'],
        ]);

        $name = data_get($input, 'name');
        $cellphone = data_get($input, 'cellphone');
        $cellphonePrefix = data_get($input, 'cellphone_prefix');
        $workEmail = data_get($input, 'work_email');
        $password = data_get($input, 'password');

        $tenant = Tenant::create([
            'name' => $name,
            'email' => $workEmail,
        ]);

        $tenant->run(function () use ($name, $cellphone, $cellphonePrefix, $workEmail, $password) {
            User::create([
                'name' => $name,
                'email' => $workEmail,
                'cellphone_number' => $cellphone,
                'cellphone_prefix' => $cellphonePrefix,
                'password' => bcrypt($password),
            ]);

            $client = new ClientRepository;

            $client->createPasswordGrantClient(null, 'Default password grant client', '');
            $client->createPersonalAccessClient(null, 'Default personal access client', '');
        });

        return TenantResource::make($tenant);
    }

    public function sendVerifyAccount(Request $request)
    {
        $input = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = data_get($input, 'email');

        $tenant = Tenant::query()
            ->where('email', $email)
            ->where('verified_email', false)
            ->first();

        if (! $tenant) {
            return response()->json([], 200);
        }

        $latestEmail = TenantVerificationEmail::query()
            ->where('tenant_id', $tenant->id)
            ->latest('id')
            ->first();

        if ($latestEmail && Carbon::parse($latestEmail->sent_at)->diffInSeconds(now()) < 60) {
            return response()->json([], 200);
        }

        $token = (string) Str::uuid();

        TenantVerificationEmail::query()
            ->create([
                'tenant_id' => $tenant->id,
                'token' => $token,
                'sent_at' => now(),
            ]);

        $link = env('CLIENT_URL').'/verify-account?token='.$token;

        JobDispatcherService::displayToFastQueue(
            new SendVerifyAccountEmail(
                email: $tenant->email,
                link: $link
            )
        );

        return response()->json([], 200);
    }

    public function verifyAccount(Request $request)
    {
        $input = $request->validate([
            'token' => ['required'],
        ]);

        $token = data_get($input, 'token');

        $tenant = TenantVerificationEmail::query()
            ->where('token', $token)
            ->first();

        if ($tenant) {
            Tenant::query()
                ->where('id', $tenant->tenant_id)
                ->update([

                    'verified_email' => true,
                ]);
        }

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

    public function sendOtp(Request $request)
    {
        $input = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = data_get($input, 'email');

        $tenant = Tenant::query()
            ->where('email', $email)
            ->first();

        if (! $tenant) {
            throw new \Exception('Tenant not found');
        }

        if ($tenant->verified_email) {
            throw ValidationException::withMessages([
                'is_verified' => 'Email already verified',
            ]);
        }

        $otp = TenantOtp::query()
            ->where('tenant_id', $tenant->id)
            ->first();

        if ($otp && $otp->sent_at->diffInSeconds(now()) < 60) {
            throw ValidationException::withMessages([
                'cannot_send' => 'Can not send OTP code again',
            ]);
        }

        JobDispatcherService::dispatch(new SendOTPCode($tenant));

        return response()->noContent();
    }

    public function verifyOtp(Request $request)
    {
        $input = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string'],
        ]);

        $email = data_get($input, 'email');
        $code = data_get($input, 'code');

        $tenant = Tenant::query()
            ->where('email', $email)
            ->first();

        if (! $tenant) {
            throw new \Exception('Tenant not found');
        }

        $otp = TenantOtp::query()
            ->where('tenant_id', $tenant->id)
            ->first();

        if (! $otp) {
            throw new \Exception('OTP code not found');
        }

        if ($otp->code !== $code) {
            throw ValidationException::withMessages([
                'code' => 'Invalid code',
            ]);
        }

        if ($otp->expire_at->lessThan(now())) {
            throw ValidationException::withMessages([
                'code' => 'OTP code has expired',
            ]);
        }

        $tenant->update([
            'verified_email' => true,
        ]);

        return TenantResource::make($tenant);
    }

    public function tenantUser(Request $request)
    {
        Log::info([DB::connection()->getDatabaseName(), DB::getDefaultConnection()]);
        $user = User::query()
            ->where('email', Tenant::current()->email)
            ->first();

        return UserResource::make($user);
    }
}
