<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RoleController extends Controller
{
    public function index(Request $request)
    {
        $roles = Role::query()
            ->with('permissions', 'user')
            ->orderBy('is_internal', 'desc')
            ->get();

        return RoleResource::collection($roles);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')],
        ]);

        $permissions = data_get($data, 'permissions', []);
        $user = Auth::user();

        $existRoleWithName = Role::query()
            ->where('name', $data['name'])
            ->exists();

        if ($existRoleWithName) {
            throw ValidationException::withMessages([
                'name' => ['A role with this name already exists.'],
            ]);
        }

        $role = Role::create([
            'name' => $data['name'],
            'guard_name' => 'api',
            'user_id' => $user->id,
        ]);

        if (! empty($permissions)) {
            $role->syncPermissions($permissions);
        }

        $role->load('permissions', 'user');

        return RoleResource::make($role);
    }

    public function show(Role $role)
    {
        $role->load('permissions');

        return RoleResource::make($role);
    }

    public function update(Request $request, Role $role)
    {
        if ($role->is_internal) {
            throw ValidationException::withMessages([
                'role' => ['This role is internal and cannot be updated.'],
            ]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', Rule::unique('roles')->ignore($role->id)],
        ]);

        $existRoleWithName = Role::query()
            ->where('name', $data['name'])
            ->where('id', '!=', $role->id)
            ->exists();

        if ($existRoleWithName) {
            throw ValidationException::withMessages([
                'name' => ['A role with this name already exists.'],
            ]);
        }

        $role->update($data);

        $role->load('permissions');

        return RoleResource::make($role);
    }

    public function destroy(Role $role)
    {
        if ($role->is_internal) {
            throw ValidationException::withMessages([
                'role' => ['This role is internal and cannot be deleted.'],
            ]);
        }

        $role->delete();

        return response()->noContent();
    }
}
