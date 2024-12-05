<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TenantResource;
use App\Jobs\CreateTenant;
use App\Jobs\SendOTPCode;
use App\Models\Tenant;
use App\Models\TenantOtp;
use Illuminate\Http\Request;
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

        SendOTPCode::dispatch(tenant: $tenant);

        CreateTenant::dispatch(
            tenant: $tenant,
            password: $password,
            email: $workEmail,
            cellphoneNumber: $cellphone,
            cellphonePrefix: $cellphonePrefix
        );

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

        SendOTPCode::dispatch(tenant: $tenant);

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
}
