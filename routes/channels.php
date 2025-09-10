<?php

use App\Models\Tenant;
use App\Models\User;
use App\Models\Waba;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('tenant.{tenantId}.waba.{wabaId}.conversation', function ($user, $tenantId, $wabaId) {
    $tenant = Tenant::find($tenantId);
    if (! $tenant) {
        return false;
    }

    tenancy()->initialize($tenant);

    $waba = Waba::find($wabaId);
    if (! $waba) {
        return false;
    }

    return Auth::check() && Auth::id() === $user->id;
});

Broadcast::channel('tenant.{tenantId}.waba.{wabaId}.user.{userId}.conversation', function ($user, $tenantId, $wabaId, $userId) {
    $tenant = Tenant::find($tenantId);
    if (! $tenant) {
        return false;
    }

    tenancy()->initialize($tenant);

    $waba = Waba::find($wabaId);
    if (! $waba) {
        return false;
    }

    // Check if the user exists in this tenant
    $userExists = User::query()
        ->where('tenant_id', $tenant->id)
        ->where('id', $userId)
        ->exists();

    if (! $userExists) {
        return false;
    }

    // User can only listen to their own channel
    return Auth::check() && (string) Auth::id() === $userId;
});
