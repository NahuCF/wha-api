<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\BroadcastResource;
use App\Models\Broadcast;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BroadcastController extends Controller
{
    public function index()
    {
        //
    }

    public function store(Request $request)
    {
        $input = $request->validate([
            'name' => ['required', 'string'],
            'send_at' => ['required', 'date'],
            'follow_whatsapp_business_policy' => ['required', 'boolean'],
            'template_id' => ['required', 'ulid', Rule::exists('templates', 'id')],
            'group_id' => ['required', 'ulid', Rule::exists('groups', 'id')],
        ]);

        $name = data_get($input, 'name');
        $sendAt = data_get($input, 'send_at');
        $followWhatsappBusinessPolicy = data_get($input, 'follow_whatsapp_business_policy');
        $templateId = data_get($input, 'template_id');
        $groupId = data_get($input, 'group_id');

        $user = Auth::user();

        $nameExist = Broadcast::query()
            ->where('name', $name)
            ->exists();

        if ($nameExist) {
            throw ValidationException::withMessages(['name' => 'Broadcast name already exists']);
        }

        $broadcast = Broadcast::query()
            ->create([
                'name' => $name,
                'send_at' => $sendAt,
                'follow_whatsapp_business_policy' => $followWhatsappBusinessPolicy,
                'user_id' => $user->id,
                'template_id' => $templateId,
                'group_id' => $groupId,
            ]);

        return BroadcastResource::make($broadcast);
    }

    public function show(Broadcast $broadcast)
    {
        //
    }

    public function update(Request $request, Broadcast $broadcast)
    {
        //
    }

    public function destroy(Broadcast $broadcast)
    {
        //
    }
}
