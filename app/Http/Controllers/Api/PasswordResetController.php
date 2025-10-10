<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendPasswordResetEmail;
use App\Models\TenantSettings;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    public function forgotPassword(Request $request)
    {
        $input = $request->validate([
            'email' => ['required', 'email'],
            'tenant_id' => ['required', 'string'],
        ]);

        $email = data_get($input, 'email');
        $tenantId = data_get($input, 'tenant_id');

        $tenant = \App\Models\Tenant::find($tenantId);
        if (! $tenant) {
            return response()->json([
                'message' => 'Invalid tenant.',
            ], 400);
        }

        tenancy()->initialize($tenant);

        $user = User::where('email', $email)
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $user) {
            return response()->json([
                'message' => 'If the email exists, a password reset link has been sent.',
                'message_code' => 'reset_password_email_sended',
            ], 200);
        }

        DB::table('password_resets')
            ->where('email', $email)
            ->where('tenant_id', $tenantId)
            ->delete();

        $token = Str::random(60);

        DB::table('password_resets')->insert([
            'email' => $email,
            'tenant_id' => $tenantId,
            'token' => $token,
            'created_at' => now(),
        ]);

        $tenantSettings = TenantSettings::where('tenant_id', $tenant->id)->first();
        $locale = $tenantSettings->language ?? 'en';

        $resetLink = config('app.client_url').'/reset-password?token='.$token;

        dispatch(new SendPasswordResetEmail(
            toEmail: $email,
            name: $user->name,
            resetLink: $resetLink,
            locale: $locale
        ));

        return response()->json([
            'message' => 'If the email exists, a password reset link has been sent.',
            'message_code' => 'reset_password_email_sended',
        ], 200);
    }

    public function resetPassword(Request $request)
    {
        $input = $request->validate([
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8'],
            'password_confirmation' => ['required', 'same:password'],
        ]);

        $passwordReset = DB::table('password_resets')
            ->where('token', $input['token'])
            ->first();

        if (! $passwordReset) {
            return response()->json([
                'message' => 'Invalid token',
                'message_code' => 'invalid_token',
            ], 200);
        }

        $tenantId = $passwordReset->tenant_id;

        $tenant = \App\Models\Tenant::find($tenantId);
        if (! $tenant) {
            throw ValidationException::withMessages([
                'token' => ['Invalid token.'],
            ]);
        }

        tenancy()->initialize($tenant);

        if (Carbon::parse($passwordReset->created_at)->addHours(24)->isPast()) {
            return response()->json([
                'message' => 'Expired token',
                'message_code' => 'expired_token',
            ], 200);
        }

        $user = User::where('email', $passwordReset->email)
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => ['User not found.'],
            ]);
        }

        $user->update([
            'password' => bcrypt($input['password']),
        ]);

        DB::table('password_resets')
            ->where('token', $input['token'])
            ->delete();

        return response()->noContent();
    }

    /**
     * Validate reset token
     */
    public function validateResetToken(Request $request)
    {
        $input = $request->validate([
            'token' => ['required', 'string'],
        ]);

        // Direct database lookup using the token
        $passwordReset = DB::table('password_resets')
            ->where('token', $input['token'])
            ->first();

        if (! $passwordReset) {
            return response()->json([
                'valid' => false,
                'message' => 'Invalid token.',
            ], 200);
        }

        // Check if token is not expired (24 hours)
        if (Carbon::parse($passwordReset->created_at)->addHours(24)->isPast()) {
            return response()->json([
                'valid' => false,
                'message' => 'Token has expired.',
            ], 200);
        }

        return response()->json([
            'valid' => true,
            'message' => 'Token is valid.',
            'email' => $passwordReset->email,
            'tenant_id' => $passwordReset->tenant_id,
        ], 200);
    }
}
