<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BotVariableResource;
use App\Models\BotVariable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BotVariableController extends Controller
{
    public function index()
    {
        $variables = BotVariable::orderBy('name')
            ->get();

        return BotVariableResource::collection($variables);
    }

    public function store(Request $request)
    {
        $input = $request->validate([
            'name' => ['required', 'string', 'max:255', 'regex:/^[a-z_][a-z0-9_]*$/'],
        ]);

        $tenantId = Auth::user()->tenant_id;
        $name = data_get($input, 'name');

        $botVariableName = BotVariable::query()
            ->where('name', $name)
            ->exists();

        if ($botVariableName) {
            return response()->json([
                'message' => 'Bot variable name already exists',
                'message_code' => 'bot_variable_name_already_exists',
            ], 422);
        }


        $variable = BotVariable::create([
            'tenant_id' => $tenantId,
            'name' => $name,
        ]);

        return BotVariableResource::make($variable);
    }

    public function update(Request $request, BotVariable $variable)
    {
        $input = $request->validate([
            'name' => ['sometimes', 'string', 'max:255', 'regex:/^[a-z_][a-z0-9_]*$/'],
        ]);

        $variable->update($input);

        return BotVariableResource::make($variable);
    }

    public function destroy(BotVariable $variable)
    {
        $variable->delete();

        return response()->json(['message' => 'Variable deleted successfully']);
    }
}