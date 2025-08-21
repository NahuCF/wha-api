<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GroupResource;
use App\Models\Group;
use App\Services\ContactService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GroupController extends Controller
{
    public function index(Request $request)
    {
        $input = $request->validate([
            'search' => ['sometimes'],
        ]);

        $search = data_get($input, 'search');

        $groups = Group::query()
            ->withCount('contacts')
            ->with('user')
            ->when($search && ! empty($search), function ($query) use ($search) {
                $query->where('name', 'ILIKE', '%'.$search.'%');
            })
            ->simplePaginate();

        return GroupResource::collection($groups);
    }

    public function show(Request $request, Group $group)
    {
        return new GroupResource($group);
    }

    public function store(Request $request)
    {
        $input = $request->validate([
            'name' => ['required', 'string'],
            'filters' => ['required', 'array'],
        ]);

        $name = data_get($input, 'name');
        $filters = data_get($input, 'filters');

        $user = Auth::user();

        $nameExist = Group::query()
            ->where('name', $name)
            ->exists();

        if ($nameExist) {
            throw ValidationException::withMessages(['name' => 'Group name already exists']);
        }

        $contacts = (new ContactService)->index(
            columns: ['id'],
            filters: $filters,
            simplePaginate: false
        );

        $contactIds = $contacts->pluck('id')->toArray();

        if (empty($contactIds)) {
            throw ValidationException::withMessages(['filters' => 'No contacts found']);
        }

        $group = Group::query()
            ->create([
                'id' => Str::ulid(),
                'name' => $name,
                'user_id' => $user->id,
                'tenant_id' => tenant('id'),
                'filters' => json_encode($filters),
            ]);

        $group->contacts()->sync($contactIds);

        $group->load('contacts');

        return new GroupResource($group);
    }

    public function update(Request $request, Group $group)
    {
        $input = $request->validate([
            'name' => ['required', 'string'],
            'filters' => ['required', 'array'],
        ]);

        $name = data_get($input, 'name');
        $filters = data_get($input, 'filters');

        $user = Auth::user();

        $nameExist = Group::query()
            ->where('name', $name)
            ->where('id', '!=', $group->id)
            ->exists();

        if ($nameExist) {
            throw ValidationException::withMessages(['name' => 'Group name already exists']);
        }

        $group->update([
            'name' => $name,
            'user_id' => $user->id,
            'filters' => json_encode($filters),
        ]);

        $contacts = (new ContactService)->index(
            columns: ['id'],
            filters: $filters,
            simplePaginate: false
        );

        $contactIds = $contacts->pluck('id')->toArray();

        if (empty($contactIds)) {
            throw ValidationException::withMessages(['filters' => 'No contacts found']);
        }

        $group->contacts()->sync($contactIds);

        $group->load('contacts');

        return new GroupResource($group);
    }

    public function destroy(Group $group)
    {
        if ($group->tenant_id !== tenant('id')) {
            throw new HttpResponseException(response()->json([
                'message' => 'This action is unauthorized.',
            ], 403));
        }

        $group->delete();

        return response()->noContent();
    }
}
