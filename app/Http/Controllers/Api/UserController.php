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
        ]);

        $onlyTrashed = data_get($input, 'only_trashed', false);
        $search = data_get($input, 'search', '');

        $users = User::query()
            ->with('permissions', 'teams')
            ->when($onlyTrashed, fn ($q) => $q->onlyTrashed())
            ->when($search, fn ($q, $search) => $q->where('name', 'ILIKE', "%{$search}%"))
            ->toSql();

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
        ]);

        $name = data_get($input, 'name');
        $email = data_get($input, 'email');
        $role = data_get($input, 'role');
        $teamsIds = data_get($input, 'team_ids', []);

        $tenant = tenant();

        if ($role == SystemRole::OWNER->value) {
            throw ValidationException::withMessages([
                'role' => ['You can not create an owner user.'],
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

        $user->syncRoles($role);
        $user->givePermissionTo(Role::findByName($role)->permissions);

        $user->load('roles', 'permissions', 'teams');

        return UserResource::make($user);
    }

    public function show(User $user)
    {
        $user->load('teams');

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
        ]);

        $name = data_get($input, 'name');
        $email = data_get($input, 'email');
        $role = data_get($input, 'role');
        $teamsIds = data_get($input, 'team_ids', []);

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

        $user->syncRoles($role);
        $user->givePermissionTo(Role::findByName($role)->permissions);

        $user->load('roles', 'permissions', 'teams');

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
