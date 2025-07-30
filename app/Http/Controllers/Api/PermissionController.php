<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PermissionResource;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function index()
    {
        $permissions = Cache::remember('permissions', 60 * 24, fn () => Permission::all());

        $grouped = Cache::remember('grouped_permissions', 60 * 24, function () use ($permissions) {
            return $permissions
                ->map(function ($item) {
                    return collect($item)->only(['name', 'label', 'description']);
                })
                ->groupBy(function ($item) {
                    return explode('.', $item['name'])[0];
                });
        });

        return PermissionResource::collection($permissions)->additional([
            'meta' => [
                'groups' => $grouped,
            ],
        ]);
    }
}
