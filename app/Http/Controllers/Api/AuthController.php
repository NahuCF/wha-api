<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\CreateTenant;
use App\Jobs\SendOTPCode;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            'work_email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $businessName = data_get($input, 'business_name');
        $businessWebsite = data_get($input, 'business_website');
        $name = data_get($input, 'name');
        $cellphone = data_get($input, 'cellphone');
        $cellphonePrefix = data_get($input, 'cellphone_prefix');
        $workEmail = data_get($input, 'work_email');
        $password = data_get($input, 'password');

        $tenantFullEmail = Tenant::query()
            ->orWhere('email', $workEmail)
            ->first();

        if ($tenantFullEmail) {
            return response()->json(['data' => $tenantFullEmail]);
        }

        $tenantSameBusiness = Tenant::query()
            ->orWhere('email', 'LIKE', '%@'.explode('@', $workEmail)[1])
            ->first();

        if ($tenantSameBusiness) {
            return response()->json([
                'data' => $tenantSameBusiness,
            ]);
        }

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

        DB::statement("CREATE DATABASE $tenant->database");

        $tenant->makeCurrent();
        CreateTenant::dispatch(
            tenant: $tenant,
            password: $password,
            email: $workEmail,
            cellphoneNumber: $cellphone,
            cellphonePrefix: $cellphonePrefix
        );

        return response()->json($tenant);
    }
}
