<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GroupResource;
use App\Jobs\ProcessGroupContacts;
use App\Models\Group;
use App\Services\ContactService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GroupController extends Controller
{
    public function index(Request $request)
    {
        $input = $request->validate([
            'search' => ['sometimes'],
            'rows_per_page' => ['sometimes', 'integer'],
        ]);

        $search = data_get($input, 'search');
        $rowsPerPage = data_get($input, 'rows_per_page', 10);

        $groups = Group::query()
            ->withCount('contacts')
            ->with('user')
            ->when($search && ! empty($search), function ($query) use ($search) {
                $query->where('name', 'ILIKE', '%'.$search.'%');
            })
            ->paginate($rowsPerPage);

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

        $contactsQuery = (new ContactService)->getFilteredQuery($filters);
        $hasContacts = $contactsQuery->exists();

        if (! $hasContacts) {
            throw ValidationException::withMessages(['filters' => 'No contacts found']);
        }

        $contactsCount = $contactsQuery->count();

        $group = Group::query()
            ->create([
                'id' => Str::ulid(),
                'name' => $name,
                'user_id' => $user->id,
                'tenant_id' => tenant('id'),
                'filters' => json_encode($filters),
            ]);

        if ($contactsCount > 5000) {
            ProcessGroupContacts::dispatch($group, $filters)->onQueue('heavy');

            return response()->json('', 200);
        } else {
            $contacts = $contactsQuery->select('id')->get();

            $pivotData = $contacts->map(function ($contact) use ($group) {
                return [
                    'group_id' => $group->id,
                    'contact_id' => $contact->id,
                ];
            })->toArray();

            DB::table('contact_group')->insert($pivotData);

            $group->update(['contacts_count' => $contacts->count()]);

            return new GroupResource($group);
        }
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

        $contactsQuery = (new ContactService)->getFilteredQuery($filters);
        $hasContacts = $contactsQuery->exists();

        if (! $hasContacts) {
            throw ValidationException::withMessages(['filters' => 'No contacts found']);
        }

        $contactsCount = $contactsQuery->count();

        $group->update([
            'name' => $name,
            'user_id' => $user->id,
            'filters' => json_encode($filters),
        ]);

        if ($contactsCount > 5000) {
            ProcessGroupContacts::dispatch($group, $filters)->onQueue('heavy');

            return response()->json('', 200);
        } else {
            DB::table('contact_group')->where('group_id', $group->id)->delete();

            $contacts = $contactsQuery->select('id')->get();

            $pivotData = $contacts->map(function ($contact) use ($group) {
                return [
                    'group_id' => $group->id,
                    'contact_id' => $contact->id,
                ];
            })->toArray();

            DB::table('contact_group')->insert($pivotData);

            $group->update(['contacts_count' => $contacts->count()]);

            return new GroupResource($group);
        }
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
