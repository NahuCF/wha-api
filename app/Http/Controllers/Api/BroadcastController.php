<?php

namespace App\Http\Controllers\Api;

use App\Enums\BroadcastStatus;
use App\Http\Resources\BroadcastResource;
use App\Models\Broadcast;
use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BroadcastController extends Controller
{
    public function index(Request $request)
    {
        $input = $request->validate([
            'phone_number_id' => ['required', 'ulid', Rule::exists('phone_numbers', 'id')],
            'status' => ['sometimes', 'in:'.implode(',', BroadcastStatus::values())],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date'],
            'search' => ['sometimes', 'string'],
            'rows_per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $phoneNumberId = data_get($input, 'phone_number_id');
        $status = data_get($input, 'status');
        $startDate = data_get($input, 'start_date');
        $endDate = data_get($input, 'end_date');
        $search = data_get($input, 'search');
        $rowsPerPage = data_get($input, 'rows_per_page', 20);

        $broadcasts = Broadcast::query()
            ->with(['user'])
            ->where('phone_number_id', $phoneNumberId)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($startDate && $endDate, fn ($q) => $q->whereBetween('created_at', [$startDate, $endDate]))
            ->when($search, fn ($q) => $q->where('name', 'ILIKE', '%'.$search.'%'))
            ->orderBy('created_at', 'desc')
            ->simplePaginate($rowsPerPage);

        return BroadcastResource::collection($broadcasts);

    }

    public function overview(Request $request)
    {
        $input = $request->validate([
            'phone_number_id' => ['required', 'ulid', Rule::exists('phone_numbers', 'id')],
            'overview_days' => ['required', 'integer', 'min:1'],
        ]);

        $phoneNumberId = data_get($input, 'phone_number_id');
        $overviewDays = data_get($input, 'overview_days');

        $endDate = now();
        $startDate = now()->subDays($overviewDays);

        $broadcasts = Broadcast::query()
            ->where('phone_number_id', $phoneNumberId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $totals = [
            'recipients_count' => $broadcasts->sum('recipients_count'),
            'sent_count' => $broadcasts->sum('sent_count'),
            'delivered_count' => $broadcasts->sum('delivered_count'),
            'readed_count' => $broadcasts->sum('readed_count'),
            'replied_count' => $broadcasts->sum('replied_count'),
            'failed_count' => $broadcasts->sum('failed_count'),
        ];

        $result = [];
        foreach ($totals as $key => $value) {
            $percentage = 0;
            if ($totals['recipients_count'] > 0 && $key !== 'recipients_count') {
                $percentage = round(($value / $totals['recipients_count']) * 100, 2);
            }

            $result[$key] = [
                'count' => $value,
                'percentage' => $key === 'recipients_count' ? 100 : $percentage,
            ];
        }

        return response()->json($result);
    }

    public function store(Request $request)
    {
        $input = $request->validate([
            'name' => ['required', 'string'],
            'scheduled_at' => ['sometimes', 'date'],
            'phone_number_id' => ['required', 'ulid', Rule::exists('phone_numbers', 'id')],
            'template_id' => ['nullable', 'ulid', Rule::exists('templates', 'id')],
            'group_ids' => ['required', 'array', 'min:1', Rule::exists('groups', 'id')],
            'variables' => ['nullable', 'array'],
        ]);

        $name = data_get($input, 'name');
        $scheduledAt = data_get($input, 'scheduled_at');
        $templateId = data_get($input, 'template_id');
        $groupIds = data_get($input, 'group_ids', []);
        $variables = data_get($input, 'variables', []);
        $phoneNumberId = data_get($input, 'phone_number_id');

        $nameExist = Broadcast::query()
            ->where('name', $name)
            ->exists();

        $user = Auth::user();

        if ($nameExist) {
            throw ValidationException::withMessages(['name' => 'Broadcast name already exists']);
        }

        $totalRecipients = 0;

        if (! empty($groupIds)) {
            $groupContacts = Group::whereIn('id', $groupIds)
                ->withCount('contacts')
                ->get();

            foreach ($groupContacts as $group) {
                $totalRecipients += $group->contacts_count;
            }
        }

        $broadcast = Broadcast::query()
            ->create([
                'name' => $name,
                'phone_number_id' => $phoneNumberId,
                'user_id' => $user->id,
                'scheduled_at' => $scheduledAt,
                'template_id' => $templateId,
                'variables' => $variables,
                'status' => $scheduledAt ? BroadcastStatus::SCHEDULED : BroadcastStatus::QUEUED,
                'recipients_count' => $totalRecipients,
            ]);

        $broadcast->groups()->attach($groupIds);

        $broadcast->load(['user']);

        return BroadcastResource::make($broadcast);
    }

    public function updateStatus(Request $request, Broadcast $broadcast)
    {
        $input = $request->validate([
            'status' => ['required', 'string', Rule::in(BroadcastStatus::values())],
        ]);

        $status = data_get($input, 'status');

        $broadcast->update([
            'status' => $status,
        ]);

        return BroadcastResource::make($broadcast);
    }

    public function show(Broadcast $broadcast)
    {
        $broadcast->load(['user', 'groups', 'phoneNumber']);

        return BroadcastResource::make($broadcast);
    }

}
