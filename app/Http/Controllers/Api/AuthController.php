<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TenantResource;
use App\Http\Resources\UserResource;
use App\Jobs\CreateTenant;
use App\Jobs\SendOTPCode;
use App\Models\Tenant;
use App\Models\TenantOtp;
use App\Models\User;
use App\Services\JobDispatcherService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $input = $request->validate([
            'business_name' => ['required', 'string'],
            'business_website' => ['required', 'string'],
            'name' => ['required', 'string'],
            'cellphone' => ['required', 'string'],
            'cellphone_prefix' => ['required', 'string'],
            'work_email' => ['required', 'email', 'unique:tenants,email'],
            'password' => ['required', 'string'],
        ]);

        $businessName = data_get($input, 'business_name');
        $businessWebsite = data_get($input, 'business_website');
        $name = data_get($input, 'name');
        $cellphone = data_get($input, 'cellphone');
        $cellphonePrefix = data_get($input, 'cellphone_prefix');
        $workEmail = data_get($input, 'work_email');
        $password = data_get($input, 'password');

        $randomString = substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 6);
        $databaseName = $randomString.'_'.strtolower($businessName);

        $tenant = Tenant::create([
            'name' => $name,
            'website' => $businessWebsite,
            'business_name' => $businessName,
            'email' => $workEmail,
            'database' => $databaseName,
        ]);

        JobDispatcherService::dispatch(new SendOTPCode($tenant));

        JobDispatcherService::dispatch(
            new CreateTenant(
                tenant: $tenant,
                password: $password,
                email: $workEmail,
                cellphoneNumber: $cellphone,
                cellphonePrefix: $cellphonePrefix
            )
        );

        return TenantResource::make($tenant);
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
