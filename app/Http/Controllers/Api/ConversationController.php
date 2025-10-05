<?php

namespace App\Http\Controllers\Api;

use App\Enums\ConversationActivityType;
use App\Events\ConversationNew;
use App\Events\ConversationOwnerChanged;
use App\Http\Controllers\Controller;
use App\Http\Resources\ConversationActivityResource;
use App\Http\Resources\ConversationResource;
use App\Models\Bot;
use App\Models\Conversation;
use App\Models\ConversationActivity;
use App\Services\BotService;
use App\Services\ConversationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ConversationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $input = $request->validate([
            'rows_per_page' => ['nullable', 'integer', 'min:1'],
            'only_unassigned' => ['nullable', 'boolean'],
            'user_id' => ['nullable', 'exists:users,id'],
            'only_pinned' => ['nullable', 'boolean'],
            'only_solved' => ['nullable', 'boolean'],
            'only_opened' => ['nullable', 'boolean'],
            'only_mentioned' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'min:1'],
            'search_type' => ['nullable', 'in:contact,message'],
            'messages_per_page' => ['nullable', 'integer', 'min:1'],
        ]);

        $user = Auth::user();
        $conversations = (new ConversationService)->searchConversations($input, $user);

        return ConversationResource::collection($conversations);
    }

    public function activities(Conversation $conversation)
    {
        $activities = ConversationActivity::query()
            ->where('conversation_id', $conversation->id)
            ->orderBy('id', 'asc')
            ->get();

        return ConversationActivityResource::collection($activities);
    }

    public function show(Conversation $conversation)
    {
        $conversation->load(['contact', 'assignedUser', 'latestMessage', 'waba', 'phoneNumber']);

        return new ConversationResource($conversation);
    }

    public function changeSolved(Request $request, Conversation $conversation)
    {
        $input = $request->validate([
            'is_solved' => ['required', 'boolean'],
        ]);

        $user = Auth::user();
        $isSolved = data_get($input, 'is_solved');

        if ($isSolved) {
            $conversation->is_solved = true;
            $conversation->user_id = null;
        } else {
            $conversation->is_solved = false;
            $conversation->user_id = $user->id;
        }

        $conversation->save();

        ConversationActivity::create([
            'tenant_id' => tenant('id'),
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'type' => $isSolved ? ConversationActivityType::RESOLVED : ConversationActivityType::REOPENED,
            'data' => [
                'user_name' => $user->name,
            ],
        ]);

        $conversation->load(['contact', 'assignedUser', 'latestMessage', 'waba', 'phoneNumber']);

        return new ConversationResource($conversation);
    }

    public function changeOwner(Request $request, Conversation $conversation)
    {
        $input = $request->validate([
            'user_id' => ['nullable', 'exists:users,id'],
            'bot_id' => ['nullable', 'exists:bots,id'],
            'type' => ['nullable', 'in:user,bot'],
        ]);

        $user = Auth::user();
        $userId = data_get($input, 'user_id');
        $botId = data_get($input, 'bot_id');
        $type = data_get($input, 'type', 'user');

        // Check authorization for current assignment
        if ($conversation->user_id && $conversation->user_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized',
                'message_code' => 'unauthorized',
            ], 403);
        }

        $oldUser = $conversation->assignedUser;
        $oldBot = $conversation->assignedBot ?? null;

        // Handle bot assignment
        if ($type === 'bot' && $botId) {
            $bot = Bot::find($botId);
            if (! $bot || $bot->tenant_id !== tenant('id')) {
                return response()->json([
                    'message' => 'Bot not found',
                    'message_code' => 'bot_not_found',
                ], 404);
            }

            // Clear user assignment and set bot
            $conversation->user_id = null;
            $conversation->assigned_bot_id = $botId;
            $conversation->update();

            // Start bot session
            $botService = new BotService;
            $botService->startBotSession($bot, $conversation, $conversation->contact);

            $activityType = ConversationActivityType::ASSIGNED;
            $activityData = [
                'old_user_name' => $oldUser?->name,
                'old_bot_name' => $oldBot?->name,
                'new_bot_name' => $bot->name,
                'assignment_type' => 'bot',
            ];
        }
        // Handle user assignment
        elseif ($type === 'user' && $userId) {
            $conversation->user_id = $userId;
            $conversation->assigned_bot_id = null;
            $conversation->update();

            // End any active bot sessions
            \App\Models\BotSession::where('conversation_id', $conversation->id)
                ->whereIn('status', [\App\Enums\BotSessionStatus::ACTIVE, \App\Enums\BotSessionStatus::WAITING])
                ->update(['status' => \App\Enums\BotSessionStatus::COMPLETED]);

            $activityType = ConversationActivityType::ASSIGNED;
            $activityData = [
                'old_user_name' => $oldUser?->name,
                'old_bot_name' => $oldBot?->name,
                'new_user_name' => $conversation?->assignedUser?->name,
                'assignment_type' => 'user',
            ];
        }
        // Handle unassignment
        else {
            $conversation->user_id = null;
            $conversation->assigned_bot_id = null;
            $conversation->update();

            // End any active bot sessions
            \App\Models\BotSession::where('conversation_id', $conversation->id)
                ->whereIn('status', [\App\Enums\BotSessionStatus::ACTIVE, \App\Enums\BotSessionStatus::WAITING])
                ->update(['status' => \App\Enums\BotSessionStatus::COMPLETED]);

            $activityType = ConversationActivityType::UNASSIGNED;
            $activityData = [
                'old_user_name' => $oldUser?->name,
                'old_bot_name' => $oldBot?->name,
            ];
        }

        $conversation->load(['contact', 'assignedUser', 'latestMessage', 'waba', 'phoneNumber']);

        ConversationActivity::create([
            'tenant_id' => tenant('id'),
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'type' => $activityType,
            'data' => $activityData,
        ]);

        $conversationResource = new ConversationResource($conversation);

        if (! $userId && ! $botId) {
            broadcast(
                new ConversationNew(
                    conversation: $conversationResource->toArray(request()),
                    tenantId: tenant('id'),
                    wabaId: $conversation->waba_id
                )
            );
        } else {
            broadcast(
                new ConversationOwnerChanged(
                    conversation: $conversationResource->toArray(request()),
                    tenantId: tenant('id'),
                    wabaId: $conversation->waba_id,
                    newOwnerId: $userId ?? $botId
                )
            );
        }

        return $conversationResource;
    }

    public function stats(Request $request)
    {
        $input = $request->validate([
            'view' => ['required', 'string', 'in:unassigned,mine,opened,resolved,mentioned'],
        ]);

        $user = Auth::user();
        $updateView = data_get($input, 'view');

        $stats = (new ConversationService)->getConversationStats($user, $updateView);

        return response()->json([
            'data' => $stats,
        ]);
    }

    public function pin(Conversation $conversation)
    {
        $user = Auth::user();

        if ($conversation->isPinnedBy($user)) {
            return response()->json([
                'message' => 'Conversation already pinned',
                'message_code' => 'already_pinned',
            ], 422);
        }

        $maxPosition = DB::table('conversation_pins')
            ->where('user_id', $user->id)
            ->max('position') ?? -1;

        $conversation->pinFor($user, $maxPosition + 1);

        $conversation->load(['contact', 'assignedUser', 'latestMessage', 'waba', 'phoneNumber']);

        return new ConversationResource($conversation);
    }

    public function unpin(Conversation $conversation)
    {
        $user = Auth::user();

        $conversation->unpinFor($user);

        $conversation->load(['contact', 'assignedUser', 'latestMessage', 'waba', 'phoneNumber']);

        return new ConversationResource($conversation);
    }

    public function store(Request $request)
    {
        $input = $request->validate([
            'contact_id' => ['required', 'exists:contacts,id'],
            'waba_id' => ['required', 'exists:wabas,id'],
            'phone_number_id' => ['required', 'exists:phone_numbers,id'],
            'to_phone' => ['required', 'string'],
        ]);

        $user = Auth::user();

        $contactId = data_get($input, 'contact_id');
        $wabaId = data_get($input, 'waba_id');
        $phoneNumberId = data_get($input, 'phone_number_id');
        $toPhone = data_get($input, 'to_phone');

        $conversationQuery = Conversation::query()
            ->where('contact_id', $contactId)
            ->where('waba_id', $wabaId)
            ->where('phone_number_id', $phoneNumberId)
            ->where('to_phone', $toPhone);

        // Check if there is an active conversation
        $activeConversation = $conversationQuery
            ->clone()
            ->where('expires_at', '>', now())
            ->first();

        if ($activeConversation) {
            return response()->json([
                'message' => 'Exist active conversation',
                'message_code' => 'exist_active_conversation',
                'data' => [
                    'conversation_id' => $activeConversation->id,
                    'assigned_user_name' => $activeConversation?->assignedUser?->name,
                ],
            ]);
        }

        // Check if exist the conversation
        $draftConversation = $conversationQuery
            ->clone()
            ->whereNull('expires_at')
            ->first();

        if ($draftConversation) {
            return response()->json([
                'message' => 'Exist draft conversation',
                'message_code' => 'exist_draft_conversation',
                'data' => [
                    'conversation_id' => $draftConversation->id,
                ],
            ]);
        }

        // Create a conversation but do not start it (expries at = null)
        $conversation = Conversation::create([
            'contact_id' => $contactId,
            'waba_id' => $wabaId,
            'user_id' => $user->id,
            'phone_number_id' => $phoneNumberId,
            'to_phone' => $toPhone,
        ]);

        $conversation->load(['contact', 'assignedUser', 'latestMessage', 'waba', 'phoneNumber']);

        $conversationResource = new ConversationResource($conversation);

        broadcast(new ConversationNew(
            $conversationResource->toArray(request()),
            tenant('id'),
            $wabaId
        ));

        return $conversationResource;
    }
}
