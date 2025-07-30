<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TeamResource;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TeamController extends Controller
{
    public function index()
    {
        $users = Team::query()
            ->with('users', 'owner')
            ->withCount('users')
            ->get();

        return TeamResource::collection($users);
    }

    public function show(Team $team)
    {
        $team->load('users', 'owner');

        return TeamResource::make($team);
    }

    public function update(Request $request, Team $team)
    {
        $input = $request->validate([
            'name' => ['required', 'string'],
            'user_ids' => ['array'],
            'user_ids.*' => ['ulid', Rule::exists('users', 'id')],
        ]);

        $name = data_get($input, 'name');
        $userIds = data_get($input, 'user_ids', []);

        $nameAlreadyExists = Team::query()
            ->where('name', $name)
            ->where('id', '!=', $team->id)
            ->exists();

        if ($nameAlreadyExists) {
            throw ValidationException::withMessages([
                'name' => ['A team with this name already exists.'],
            ]);
        }

        $team->update([
            'name' => $name,
        ]);

        $team->users()->sync($userIds);

        $team->load('users');

        return TeamResource::make($team);
    }

    public function store(Request $request)
    {
        $input = $request->validate([
            'name' => ['required', 'string'],
            'user_ids' => ['array'],
            'user_ids.*' => ['ulid', Rule::exists('users', 'id')],
        ]);

        $user = Auth::user();

        $name = data_get($input, 'name');
        $userIds = data_get($input, 'user_ids', []);

        $nameAlreadyExists = Team::query()
            ->where('name', $name)
            ->exists();

        if ($nameAlreadyExists) {
            throw ValidationException::withMessages([
                'name' => ['A team with this name already exists.'],
            ]);
        }

        $team = Team::query()
            ->create([
                'name' => $name,
                'user_id' => $user->id,
            ]);

        $team->users()->sync($userIds);

        $team->load('users');

        return TeamResource::make($team);
    }

    public function destroy(Team $team)
    {
        $team->delete();

        return response()->noContent();
    }
}
