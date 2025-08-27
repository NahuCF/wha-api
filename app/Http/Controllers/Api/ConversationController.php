<?php

namespace App\Http\Controllers\Api;

use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
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
        ]);

        $user = Auth::user();

        $rowsPerPage = data_get($input, 'rows_per_page', 15);
        $onlyUnassigned = data_get($input, 'only_unassigned');
        $userId = data_get($input, 'user_id');
        $onlyPinned = data_get($input, 'only_pinned');
        $onlySolved = data_get($input, 'only_solved');
        $onlyOpened = data_get($input, 'only_opened');

        $conversations = Conversation::query()
            ->with(['contact', 'assignedUser', 'latestMessage', 'waba'])
            ->when($onlyUnassigned, fn ($q) => $q->whereNull('user_id'))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when($onlyPinned && $user, fn ($q) => $q->pinnedBy($user))
            ->when($onlySolved, fn ($q) => $q->where('is_solved', true))
            ->when($onlyOpened, fn ($q) => $q->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            }))
            ->when($user, fn ($q) => $q->with(['pinnedByUsers' => function ($query) use ($user) {
                $query->where('user_id', $user->id);
            }]))
            ->orderBy('last_message_at', 'desc')
            ->simplePaginate($rowsPerPage);

        return ConversationResource::collection($conversations);
    }

    public function store(Request $request): ConversationResource
    {
        $input = $request->validate([
            'contact_id' => ['required', 'exists:contacts,id'],
            'waba_id' => ['required', 'exists:wabas,id'],
            'meta_id' => ['required', 'string', 'unique:conversations,meta_id'],
            'user_id' => ['nullable', 'exists:users,id'],
            'is_solved' => ['nullable', 'boolean'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $user = Auth::user();
        $contactId = data_get($input, 'contact_id');
        $wabaId = data_get($input, 'waba_id');
        $metaId = data_get($input, 'meta_id');
        $userId = data_get($input, 'user_id');
        $isSolved = data_get($input, 'is_solved', false);
        $expiresAt = data_get($input, 'expires_at');

        $conversation = Conversation::create([
            'contact_id' => $contactId,
            'waba_id' => $wabaId,
            'meta_id' => $metaId,
            'user_id' => $userId,
            'is_solved' => $isSolved,
            'expires_at' => $expiresAt,
            'last_message_at' => now(),
            'unread_count' => 0,
        ]);

        $conversation->load(['contact', 'assignedUser', 'latestMessage', 'waba']);

        if ($user) {
            $conversation->load(['pinnedByUsers' => function ($query) use ($user) {
                $query->where('user_id', $user->id);
            }]);
        }

        return new ConversationResource($conversation);
    }

    public function sendMessage(Request $request)
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:500'],
            'user' => ['required', 'string', 'max:100'],
        ]);

        broadcast(new MessageSent(
            $validated['message'],
            $validated['user'],
            now()->toISOString()
        ))->toOthers();

        return response()->json([
            'status' => 'Message sent successfully',
            'data' => $validated,
        ]);
    }
}
