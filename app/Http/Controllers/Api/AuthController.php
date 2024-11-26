<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\ClientRepository;

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

        try {
            $randomString = substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 6);
            $databaseName = $randomString.'_'.strtolower($businessName);

            $tenant = Tenant::create([
                'name' => $name,
                'email' => $workEmail,
                'database' => $databaseName, // Customize as needed
            ]);
            $databaseName = $tenant->database;

            DB::statement("CREATE DATABASE $databaseName");

            $tenant->makeCurrent();
            DB::setDefaultConnection('tenant');

            Artisan::call('migrate', [
                '--path' => 'database/migrations/tenant',
                '--database' => 'tenant',
                '--force' => true,
            ]);

            $clientRepository = new ClientRepository;
            $client = $clientRepository->createPersonalAccessClient(
                null, 'Client for '.$businessName, 'http://localhost'
            );

            $user = User::create([
                'name' => $name,
                'email' => $workEmail,
                'password' => Hash::make($password),
            ]);

            // Step 5: Generate a personal access token for the admin user
            $token = $user->createToken('token-api')->accessToken;

            return response()->json([
                'tenant' => $tenant,
                'admin_user' => $user,
                'token' => $token,
            ], 201);
        } catch (Exception $e) {
            return response($e);
        }

        return response([$tenant, app('currentTenant')]);

        // Switch to the tenant database

        return response($request->all());
    }
}
