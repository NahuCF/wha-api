<?php

namespace App\Http\Controllers\Api;

use App\Enums\SystemRole;
use App\Enums\UserStatus;
use App\Helpers\AppEnvironment;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $input = $request->validate([
            'only_trashed' => ['sometimes', 'boolean'],
            'search' => ['sometimes', 'string'],
            'waba_id' => ['sometimes', 'ulid', Rule::exists('wabas', 'id')],
        ]);

        $onlyTrashed = data_get($input, 'only_trashed', false);
        $search = data_get($input, 'search', '');
        $wabaId = data_get($input, 'waba_id');

        $users = User::query()
            ->with('permissions', 'teams', 'wabas')
            ->when($onlyTrashed, fn ($q) => $q->onlyTrashed())
            ->when($search, fn ($q, $search) => $q->where('name', 'ILIKE', "%{$search}%"))
            ->when($wabaId, fn ($q) => $q->whereHas('wabas', fn ($query) => $query->where('wabas.id', $wabaId)))
            ->get();

        return UserResource::collection($users);
    }

    public function store(Request $request)
    {
        $input = $request->validate([
            'name' => ['required', 'string'],
            'email' => ['required', 'email'],
            'role' => ['required', 'string', Rule::exists('roles', 'name')],
            'team_ids' => ['sometimes', 'array'],
            'team_ids.*' => ['ulid', Rule::exists('teams', 'id')],
            'waba_ids' => ['sometimes', 'array'],
            'waba_ids.*' => ['ulid', Rule::exists('wabas', 'id')],
            'default_waba_id' => ['sometimes', 'ulid', Rule::exists('wabas', 'id')],
        ]);

        $name = data_get($input, 'name');
        $email = data_get($input, 'email');
        $role = data_get($input, 'role');
        $teamsIds = data_get($input, 'team_ids', []);
        $wabaIds = data_get($input, 'waba_ids', []);
        $defaultWabaId = data_get($input, 'default_waba_id');

        $tenant = tenant();

        if ($role == SystemRole::OWNER->value) {
            throw ValidationException::withMessages([
                'role' => ['You can not create an owner user.'],
            ]);
        }

        if ($role == SystemRole::OWNER->value && ! empty($wabaIds)) {
            throw ValidationException::withMessages([
                'waba_ids' => ['Owner users have access to all WABAs by default.'],
            ]);
        }

        $emailAlreadyExists = User::query()
            ->where('email', $email)
            ->where('tenant_id', $tenant->id)
            ->exists();

        if ($emailAlreadyExists) {
            throw ValidationException::withMessages([
                'email' => ['A user with this email already exists.'],
            ]);
        }

        $userData = [
            'name' => $name,
            'email' => $email,
            'status' => UserStatus::INVITED->value,
        ];

        if (AppEnvironment::isLocal()) {
            $userData['password'] = bcrypt('password');
            $userData['status'] = UserStatus::INVITATION_ACCEPTED->value;
        }

        $user = User::query()
            ->create($userData);

        $user->teams()->syncWithoutDetaching($teamsIds);

        if (! empty($wabaIds) && $role != SystemRole::OWNER->value) {
            $user->wabas()->sync($wabaIds);
            
            $finalDefaultWabaId = $defaultWabaId ?: $wabaIds[0] ?? null;
            if ($finalDefaultWabaId && in_array($finalDefaultWabaId, $wabaIds)) {
                $user->update(['default_waba_id' => $finalDefaultWabaId]);
            }
        }

        $user->syncRoles($role);
        $user->givePermissionTo(Role::findByName($role)->permissions);

        $user->load('roles', 'permissions', 'teams', 'wabas', 'defaultWaba');

        return UserResource::make($user);
    }

    public function show(User $user)
    {
        $user->load('teams', 'wabas', 'defaultWaba');

        return UserResource::make($user);
    }

    public function update(Request $request, User $user)
    {
        $input = $request->validate([
            'name' => ['required', 'string'],
            'email' => ['required', 'email'],
            'role' => ['required', 'string', Rule::exists('roles', 'name')],
            'team_ids' => ['sometimes', 'array'],
            'team_ids.*' => ['ulid', Rule::exists('teams', 'id')],
            'waba_ids' => ['required', 'array'],
            'waba_ids.*' => ['ulid', Rule::exists('wabas', 'id')],
        ]);

        $name = data_get($input, 'name');
        $email = data_get($input, 'email');
        $role = data_get($input, 'role');
        $teamsIds = data_get($input, 'team_ids', []);
        $wabaIds = data_get($input, 'waba_ids', []);

        $emailAlreadyExists = User::query()
            ->where('email', $email)
            ->where('id', '!=', $user->id)
            ->exists();

        if ($emailAlreadyExists) {
            throw ValidationException::withMessages([
                'email' => ['A user with this email already exists.'],
            ]);
        }

        $userData = [
            'name' => $name,
            'email' => $email,
            'status' => UserStatus::INVITED->value,
        ];

        if (AppEnvironment::isLocal()) {
            $userData['password'] = bcrypt('password');
            $userData['status'] = UserStatus::INVITATION_ACCEPTED->value;
        }

        $user->update($userData);

        $user->teams()->sync($teamsIds);

        if ($role != SystemRole::OWNER->value) {
            $user->wabas()->sync($wabaIds);

            if(count($wabaIds)== 1 ){
                $user->update(['default_waba_id' => $wabaIds[0]]);
            }
        } 

        $user->syncRoles($role);
        $user->givePermissionTo(Role::findByName($role)->permissions);

        $user->load('roles', 'permissions', 'teams', 'wabas', 'defaultWaba');

        return UserResource::make($user);
    }

    public function destroy(User $user)
    {
        if ($user->tenant_id !== tenant('id')) {
            throw new HttpResponseException(response()->json([
                'message' => 'This action is unauthorized.',
            ], 403));
        }

        if ($user->roles()->where('name', SystemRole::OWNER->value)->exists()) {
            throw ValidationException::withMessages([
                'role' => ['You can not delete an owner user.'],
            ]);
        }

        $user->delete();

        $user->update(['status' => UserStatus::DELETED->value]);

        return response()->noContent();
    }

    public function restore($id)
    {
        $user = User::withTrashed()->findOrFail($id);

        if ($user->tenant_id !== tenant('id')) {
            throw new HttpResponseException(response()->json([
                'message' => 'This action is unauthorized.',
            ], 403));
        }

        if ($user->tenant_id !== tenant('id')) {
            throw new HttpResponseException(response()->json([
                'message' => 'This action is unauthorized.',
            ], 403));
        }

        $user->restore();

        $user->update(['status' => UserStatus::INVITATION_ACCEPTED->value]);

        return UserResource::make($user);
    }
}
