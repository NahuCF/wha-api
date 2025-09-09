<?php

namespace App\Http\Controllers\Api;

use App\Enums\BroadcastStatus;
use App\Enums\TemplateStatus;
use App\Http\Resources\BroadcastResource;
use App\Jobs\ProcessBroadcast;
use App\Models\Broadcast;
use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
            ->paginate($rowsPerPage);

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
            'send_now' => ['sometimes', 'boolean'],
            'send_to_all_numbers' => ['sometimes', 'boolean'],
            'variables' => ['nullable', 'array'],
        ]);

        $name = data_get($input, 'name');
        $scheduledAt = data_get($input, 'scheduled_at');
        $templateId = data_get($input, 'template_id');
        $groupIds = data_get($input, 'group_ids', []);
        $variables = data_get($input, 'variables', []);
        $phoneNumberId = data_get($input, 'phone_number_id');
        $sendNow = data_get($input, 'send_now', false);
        $sendToAllNumbers = data_get($input, 'send_to_all_numbers', false);

        $nameExist = Broadcast::query()
            ->where('name', $name)
            ->exists();

        if ($nameExist) {
            throw ValidationException::withMessages(['name' => 'Broadcast name already exists']);
        }

        $isTemplateApproved = Template::query()
            ->where('id', $templateId)
            ->where('status', TemplateStatus::APPROVED)
            ->exists();

        if (! $isTemplateApproved) {
            return response()->json([
                'message' => 'Template is not approved',
                'message_code' => 'template_not_approved',
            ]);
        }

        $user = Auth::user();

        $totalRecipients = $this->calculateRecipientsCount($groupIds, $sendToAllNumbers);

        $broadcast = Broadcast::query()
            ->create([
                'name' => $name,
                'phone_number_id' => $phoneNumberId,
                'user_id' => $user->id,
                'scheduled_at' => $scheduledAt,
                'template_id' => $templateId,
                'variables' => $variables,
                'send_to_all_numbers' => $sendToAllNumbers,
                'status' => $scheduledAt ? BroadcastStatus::SCHEDULED : BroadcastStatus::QUEUED,
                'recipients_count' => $totalRecipients,
            ]);

        $broadcast->groups()->attach($groupIds);

        $broadcast->load(['user']);

        if ($sendNow) {
            ProcessBroadcast::dispatch($broadcast)->onQueue('broadcasts');
        }

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

    public function repeat(Request $request, Broadcast $broadcast)
    {
        $input = $request->validate([
            'scheduled_at' => ['sometimes', 'date'],
            'send_now' => ['sometimes', 'boolean'],
        ]);

        $scheduledAt = data_get($input, 'scheduled_at');
        $sendNow = data_get($input, 'send_now', false);

        if (! $scheduledAt && ! $sendNow) {
            return response()->json([
                'message' => 'Scheduled at or send now is required',
                'message_code' => 'scheduled_at_or_send_now_required',
            ]);
        }

        $groupIds = $broadcast->groups->pluck('id')->toArray();

        $isTemplateApproved = Template::query()
            ->where('id', $broadcast->template_id)
            ->where('status', TemplateStatus::APPROVED)
            ->exists();

        if (! $isTemplateApproved) {
            return response()->json([
                'message' => 'Template is not approved',
                'message_code' => 'template_not_approved',
            ], 422);
        }

        $user = Auth::user();

        $totalRecipients = $this->calculateRecipientsCount($groupIds, $broadcast->send_to_all_numbers);

        $newBroadcast = Broadcast::query()
            ->create([
                'name' => $broadcast->name,
                'phone_number_id' => $broadcast->phone_number_id,
                'user_id' => $user->id,
                'scheduled_at' => $scheduledAt,
                'template_id' => $broadcast->template_id,
                'variables' => $broadcast->variables,
                'send_to_all_numbers' => $broadcast->send_to_all_numbers,
                'status' => $scheduledAt ? BroadcastStatus::SCHEDULED : BroadcastStatus::QUEUED,
                'recipients_count' => $totalRecipients,
            ]);

        $newBroadcast->groups()->attach($groupIds);

        $newBroadcast->load(['user']);

        if ($sendNow) {
            ProcessBroadcast::dispatch($newBroadcast);
        }

        return BroadcastResource::make($newBroadcast);
    }

    /**
     * Calculate the number of recipients for a broadcast
     */
    private function calculateRecipientsCount(array $groupIds, bool $sendToAllNumbers): int
    {
        if (empty($groupIds)) {
            return 0;
        }

        $contactIds = DB::table('contact_group')
            ->whereIn('group_id', $groupIds)
            ->distinct()
            ->pluck('contact_id');

        if ($contactIds->isEmpty()) {
            return 0;
        }

        if (! $sendToAllNumbers) {
            return $contactIds->count();
        }

        // Count all unique phone numbers from these contacts
        $phoneValues = DB::table('contact_field_values')
            ->join('contact_fields', 'contact_field_values.contact_field_id', '=', 'contact_fields.id')
            ->whereIn('contact_field_values.contact_id', $contactIds)
            ->where('contact_fields.internal_name', 'Phone')
            ->pluck('contact_field_values.value');

        $uniquePhones = collect();
        foreach ($phoneValues as $phoneValue) {
            $decodedValue = is_string($phoneValue) ? json_decode($phoneValue, true) : $phoneValue;
            $phones = is_array($decodedValue) ? $decodedValue : [$decodedValue];

            foreach ($phones as $phone) {
                if (! empty($phone)) {
                    $uniquePhones->push($phone);
                }
            }
        }

        return $uniquePhones->unique()->count();
    }
}
