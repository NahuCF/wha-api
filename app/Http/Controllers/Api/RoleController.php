<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RoleController extends Controller
{
    public function index(Request $request)
    {
        $roles = Role::withoutGlobalScopes()
            ->with('permissions', 'user')
            ->where(function ($query) {
                $query->whereNull('tenant_id')
                    ->orWhere('tenant_id', tenant('id'));
            })
            ->orderBy('is_internal', 'desc')
            ->get();

        return RoleResource::collection($roles);
    }

    public function store(Request $request)
    {
        $input = $request->validate([
            'name' => ['required', 'string'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')],
        ]);

        $permissions = data_get($input, 'permissions', []);
        $user = Auth::user();

        $existRoleWithName = Role::query()
            ->where('name', $input['name'])
            ->exists();

        if ($existRoleWithName) {
            throw ValidationException::withMessages([
                'name' => ['A role with this name already exists.'],
            ]);
        }

        $role = Role::create([
            'name' => $input['name'],
            'guard_name' => 'api',
            'user_id' => $user->id,
            'tenant_id' => tenant('id'),
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

        $input = $request->validate([
            'name' => ['required', 'string'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')],
        ]);

        $name = data_get($input, 'name');
        $permissions = data_get($input, 'permissions', []);

        $existRoleWithName = Role::query()
            ->where('name', $name)
            ->where('id', '!=', $role->id)
            ->exists();

        if ($existRoleWithName) {
            throw ValidationException::withMessages([
                'name' => ['A role with this name already exists.'],
            ]);
        }

        if (! empty($permissions)) {
            $role->syncPermissions($permissions);
        }

        $role->update([
            'name' => $name]);

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

        if ($role->tenant_id !== tenant('id')) {
            throw new HttpResponseException(response()->json([
                'message' => 'This action is unauthorized.',
            ], 403));
        }

        $role->delete();

        return response()->noContent();
    }
}
