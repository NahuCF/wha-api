<?php

namespace App\Http\Controllers\Api;

use App\Enums\ConversationActivityType;
use App\Events\ConversationNew;
use App\Events\ConversationOwnerChanged;
use App\Http\Controllers\Controller;
use App\Http\Resources\ConversationActivityResource;
use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use App\Models\ConversationActivity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

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
        ]);

        $user = Auth::user();

        $rowsPerPage = data_get($input, 'rows_per_page', 15);
        $onlyUnassigned = data_get($input, 'only_unassigned');
        $userId = data_get($input, 'user_id');
        $onlyPinned = data_get($input, 'only_pinned');
        $onlySolved = data_get($input, 'only_solved');
        $onlyOpened = data_get($input, 'only_opened');
        $onlyMentioned = data_get($input, 'only_mentioned');
        $search = data_get($input, 'search');
        $searchType = data_get($input, 'search_type', 'contact');

        $conversations = Conversation::query()
            ->with(['contact', 'assignedUser', 'latestMessage', 'waba', 'phoneNumber'])
            ->when($search && $searchType === 'message', function ($q) use ($search) {
                $q->whereHas('messages', function ($query) use ($search) {
                    $query->where('content', 'ILIKE', '%'.$search.'%');
                });
            })
            ->when($search && $searchType === 'contact', function ($q) use ($search) {
                $q->whereHas('contact', function ($query) use ($search) {
                    $query->whereHas('fieldValues', function ($subQuery) use ($search) {
                        $subQuery->where('value', 'ILIKE', '%'.$search.'%')
                            ->whereHas('field', function ($fieldQuery) {
                                $fieldQuery->where('internal_name', 'Name')
                                    ->where('is_primary_field', true);
                            });
                    });
                });
            })
            ->when($onlyUnassigned, fn ($q) => $q->whereNull('user_id'))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when($onlyPinned && $user, fn ($q) => $q->pinnedBy($user))
            ->when($onlySolved, fn ($q) => $q->where('is_solved', true))
            ->when($onlyOpened, fn ($q) => $q->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            }))
            ->when($onlyMentioned && $user, fn ($q) => $q->whereHas('messages', function ($query) use ($user) {
                $query->whereRaw('EXISTS (
                    SELECT 1 FROM jsonb_array_elements(mentions::jsonb) AS elem
                    WHERE elem->>? = ?
                )', [$user->name, $user->id]);
            }))
            ->when($user, fn ($q) => $q->with(['pinnedByUsers' => function ($query) use ($user) {
                $query->where('user_id', $user->id);
            }]))
            ->orderBy('last_message_at', 'asc')
            ->paginate($rowsPerPage);

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
        return new ConversationResource($conversation);
    }

    public function changeSolved(Request $request, Conversation $conversation)
    {
        $input = $request->validate([
            'is_solved' => ['required', 'boolean'],
        ]);

        $user = Auth::user();
        $isSolved = data_get($input, 'is_solved');

        if ($conversation->user_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized',
                'message_code' => 'unauthorized',
            ], 403);
        }

        $conversation->is_solved = $isSolved;
        $conversation->update();

        ConversationActivity::create([
            'tenant_id' => tenant('id'),
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'type' => $isSolved ? ConversationActivityType::RESOLVED : ConversationActivityType::REOPENED,
            'data' => [
                'user_name' => $user->name,
            ],
        ]);

        return new ConversationResource($conversation);
    }

    public function changeOwner(Request $request, Conversation $conversation)
    {
        $input = $request->validate([
            'user_id' => ['nullable', 'exists:users,id'],
        ]);

        $user = Auth::user();
        $userId = data_get($input, 'user_id');

        if ($conversation->user_id && $conversation->user_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized',
                'message_code' => 'unauthorized',
            ], 403);
        }

        $oldUser = $conversation->assignedUser;

        $conversation->user_id = $userId;
        $conversation->update();

        $conversation->load(['contact', 'assignedUser', 'latestMessage', 'waba', 'phoneNumber']);

        ConversationActivity::create([
            'tenant_id' => tenant('id'),
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'type' => $userId ? ConversationActivityType::ASSIGNED : ConversationActivityType::UNASSIGNED,
            'data' => [
                'old_user_name' => $oldUser?->name,
                'new_user_name' => $userId ? $conversation->assignedUser->name : null,
            ],
        ]);

        $conversationResource = new ConversationResource($conversation);

        if (! $userId) {
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
                    newOwnerId: $userId
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

        if ($updateView) {
            $columnName = 'last_'.$updateView.'_view_at';
            $user->update([$columnName => now()]);
        }

        $baseQuery = Conversation::query();
        $defaultTime = now()->subYears(10);

        $lastUnassignedView = $user->last_unassigned_view_at ?? $defaultTime;
        $lastMineView = $user->last_mine_view_at ?? $defaultTime;
        $lastOpenedView = $user->last_opened_view_at ?? $defaultTime;
        $lastResolvedView = $user->last_resolved_view_at ?? $defaultTime;
        $lastMentionedView = $user->last_mentioned_view_at ?? $defaultTime;

        $stats = [
            'unassigned' => $baseQuery->clone()
                ->whereNull('user_id')
                ->where(function ($q) use ($lastUnassignedView) {
                    $q->where('created_at', '>', $lastUnassignedView)
                        ->orWhere('last_message_at', '>', $lastUnassignedView);
                })
                ->count(),
            'mine' => $baseQuery->clone()
                ->where('user_id', $user->id)
                ->where(function ($q) use ($lastMineView) {
                    $q->where('last_message_at', '>', $lastMineView)
                        ->orWhere(function ($q2) use ($lastMineView) {
                            $q2->whereNull('last_message_at')
                                ->where('created_at', '>', $lastMineView);
                        });
                })
                ->count(),
            'opened' => $baseQuery->clone()
                ->where('expires_at', '>', now())
                ->where('is_solved', false)
                ->where(function ($q) use ($lastOpenedView) {
                    $q->where('created_at', '>', $lastOpenedView)
                        ->orWhere('last_message_at', '>', $lastOpenedView);
                })
                ->count(),
            'resolved' => $baseQuery->clone()
                ->where('is_solved', true)
                ->where('updated_at', '>', $lastResolvedView)
                ->count(),
            'mentioned' => $baseQuery->clone()
                ->whereHas('messages', function ($query) use ($user, $lastMentionedView) {
                    $query->whereRaw('EXISTS (
                        SELECT 1 FROM jsonb_array_elements(mentions::jsonb) AS elem
                        WHERE elem->>? = ?
                    )', [$user->name, $user->id])
                        ->where('created_at', '>', $lastMentionedView);
                })
                ->count(),
        ];

        return response()->json([
            'data' => $stats,
        ]);
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
                    'assigned_user_name' => $activeConversation->assignedUser->name,
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
