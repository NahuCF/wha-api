<?php

namespace App\Http\Controllers\Api;

use App\Enums\ConversationActivityType;
use App\Enums\MessageDirection;
use App\Enums\MessageSource;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Enums\TemplateStatus;
use App\Events\MessageDeleted;
use App\Events\MessageNew;
use App\Http\Controllers\Controller;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Jobs\SendWhatsAppMessage;
use App\Models\Conversation;
use App\Models\ConversationActivity;
use App\Models\Message;
use App\Models\Template;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MessageController extends Controller
{
    public function index(Request $request)
    {
        $input = $request->validate([
            'conversation_id' => ['sometimes', 'exists:conversations,id'],
            'broadcast_id' => ['sometimes', 'ulid', 'exists:broadcasts,id'],
            'rows_per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'min:1'],
        ]);

        $conversationId = data_get($input, 'conversation_id');
        $broadcastId = data_get($input, 'broadcast_id');
        $rowsPerPage = data_get($input, 'rows_per_page', 20);
        $search = data_get($input, 'search');

        $messages = Message::query()
            ->with(['replyToMessage'])
            ->when($conversationId, fn ($q) => $q->where('conversation_id', $conversationId))
            ->when($broadcastId, fn ($q) => $q->where('broadcast_id', $broadcastId))
            ->when($search, fn ($q) => $q->where('content', 'ILIKE', '%'.$search.'%'))
            ->orderBy('created_at', 'desc')
            ->paginate($rowsPerPage);

        if ($search && $conversationId) {
            $matchingMessages = $messages->filter(function ($message) use ($search) {
                return stripos($message->content, $search) !== false;
            });

            if ($matchingMessages->isNotEmpty()) {
                $messageIds = $matchingMessages->pluck('id')->toArray();
                $placeholders = implode(',', array_fill(0, count($messageIds), '?'));

                $messageCounts = collect(DB::select("
                    SELECT 
                        m1.id,
                        COUNT(m2.id) as newer_count
                    FROM messages m1
                    LEFT JOIN messages m2 ON 
                        m2.conversation_id = m1.conversation_id 
                        AND m2.created_at > m1.created_at
                    WHERE m1.id IN ($placeholders)
                    GROUP BY m1.id
                ", $messageIds))
                    ->keyBy('id');

                foreach ($matchingMessages as $message) {
                    $newerMessagesCount = $messageCounts->get($message->id)?->newer_count ?? 0;
                    $positionFromEnd = $newerMessagesCount + 1;
                    $pageNumber = ceil($positionFromEnd / $rowsPerPage);

                    $message->setAttribute('search_match', [
                        'page' => $pageNumber,
                        'position_from_end' => $positionFromEnd,
                    ]);
                }
            }
        }

        return MessageResource::collection($messages);
    }

    public function storeTest(Request $request)
    {
        $input = $request->validate([
            'conversation_id' => ['required', 'exists:conversations,id'],
            'reply_to_message_id' => ['nullable', 'exists:messages,id'],
            'type' => ['required', 'in:'.implode(',', MessageType::values())],
            'content' => ['sometimes', 'string'],
            'media' => ['sometimes', 'array'],
            'contact_id' => ['required', 'exists:contacts,id'],
            'display_number' => ['required', 'string'],
        ]);

        $conversationId = data_get($input, 'conversation_id');
        $replyToMessageId = data_get($input, 'reply_to_message_id');
        $type = data_get($input, 'type');
        $displayNumber = data_get($input, 'display_number');
        $contactId = data_get($input, 'contact_id');
        $content = data_get($input, 'content');

        $conversation = Conversation::query()
            ->with(['contact', 'assignedUser', 'latestMessage', 'waba', 'phoneNumber'])
            ->where('id', $conversationId)
            ->first();

        if (! $conversation) {
            return response()->json([
                'message' => 'Conversation not found',
                'message_code' => 'conversation_not_found',
            ], 404);
        }

        $message = Message::create([
            'tenant_id' => tenant('id'),
            'conversation_id' => $conversation->id,
            'meta_id' => Str::random(10),
            'contact_id' => $contactId,
            'content' => $content,
            'direction' => MessageDirection::INBOUND,
            'reply_to_message_id' => $replyToMessageId,
            'type' => MessageType::from($type),
            'status' => MessageStatus::DELIVERED,
            'to_phone' => $displayNumber,
            'delivered_at' => now(),
        ]);

        $message->load('replyToMessage');

        broadcast(new MessageNew(
            message: $message->toArray(),
            conversation: $conversation->toArray(),
            tenantId: tenant('id'),
            wabaId: $conversation->waba->id
        ));

        return MessageResource::make($message);
    }

    public function store(Request $request)
    {
        $input = $request->validate([
            'conversation_id' => ['required', 'exists:conversations,id'],
            'template_id' => ['sometimes', 'exists:templates,id'],
            'variables' => ['nullable', 'array'],
            'reply_to_message_id' => ['nullable', 'exists:messages,id'],
            'type' => ['required', 'in:'.implode(',', MessageType::values())],
            'content' => ['sometimes', 'string'],
            'media' => ['sometimes', 'array'],
            'mentions' => ['nullable', 'array'],
            'to_phone' => ['required', 'string'],
        ]);

        $conversationId = data_get($input, 'conversation_id');
        $templateId = data_get($input, 'template_id');
        $variables = data_get($input, 'variables', []);
        $replyToMessageId = data_get($input, 'reply_to_message_id');
        $type = data_get($input, 'type');
        $content = data_get($input, 'content');
        $media = data_get($input, 'media');
        $toPhone = data_get($input, 'to_phone');
        $mentions = data_get($input, 'mentions');
        $mentionUserIds = collect($mentions)
            ->map(fn ($mention) => array_values($mention)[0])
            ->toArray();

        $now = CarbonImmutable::now();
        $nextDay = $now->addDays(1);
        $user = Auth::user();

        $conversation = Conversation::query()
            ->with(['contact', 'assignedUser', 'latestMessage', 'waba', 'phoneNumber'])
            ->where('id', $conversationId)
            ->first();

        if ($conversation->isExpired()) {
            return response()->json([
                'message' => 'Conversation is expired',
                'message_code' => 'conversation_is_expired',
            ], 422);
        }

        if ($conversation->notStarted() && ! $templateId) {
            return response()->json([
                'message' => 'Template is required',
                'message_code' => 'template_is_required',
            ], 422);

            $template = Template::find($templateId);

            if ($template->status !== TemplateStatus::APPROVED->value) {
                return response()->json([
                    'message' => 'Template is not approved',
                    'message_code' => 'template_is_not_approved',
                ], 422);
            }
        }

        // Check if havent received a message yet
        $notStarted = $conversation->notStarted();
        if ($notStarted) {
            $conversation->update([
                'last_message_at' => $now,
                'expires_at' => $nextDay,
                'started_at' => $now,
            ]);
        }

        $isTypeNote = $type === MessageType::NOTE->value;

        $message = Message::create([
            'conversation_id' => $conversationId ?: $conversation->id,
            'template_id' => $templateId,
            'variables' => $variables,
            'tenant_id' => tenant('id'),
            'contact_id' => $conversation->contact_id,
            'reply_to_message_id' => $replyToMessageId,
            'direction' => MessageDirection::OUTBOUND->value,
            'type' => $type,
            'status' => MessageStatus::PENDING->value,
            'source' => MessageSource::CHAT->value,
            'content' => $content,
            'media' => $media,
            'mentions' => $mentions,
            'to_phone' => $toPhone,
        ]);

        $message->load('replyToMessage');

        $phoneNumber = $conversation->phoneNumber;
        $waba = $conversation->waba;

        if (! $isTypeNote) {
            SendWhatsAppMessage::dispatch(
                messageData: $message->toArray(),
                tenantId: tenant('id'),
                phoneNumberId: $phoneNumber->meta_id,
                wabaId: $waba->id,
                conversationId: $conversation->id,
            );
        }

        if ($notStarted) {
            ConversationActivity::create([
                'tenant_id' => tenant('id'),
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
                'type' => ConversationActivityType::CONVERSATION_STARTED,
                'data' => ['user_name' => $user->name],
            ]);
        }

        $messageArray = $message->toArray();
        $conversationArray = $conversation->toArray();

        broadcast(new MessageNew(
            message: $messageArray,
            conversation: $conversationArray,
            tenantId: tenant('id'),
            wabaId: $waba->id
        ));

        return MessageResource::make($message)->additional([
            'meta' => [
                'conversation' => ConversationResource::make($message->conversation),
            ],
        ]);
    }

    /**
     * Test endpoint to simulate a deleted message event
     */
    public function testDeletedEvent(Request $request, Message $message)
    {
        // Load the conversation with all relationships
        $conversation = $message->conversation()
            ->with(['contact', 'assignedUser', 'latestMessage', 'waba', 'phoneNumber'])
            ->first();

        if (! $conversation) {
            return response()->json(['error' => 'Conversation not found'], 404);
        }

        // Update message status to DELETED
        $message->update([
            'status' => MessageStatus::DELETED,
            'deleted_at' => now(),
        ]);

        broadcast(new MessageDeleted(
            messageId: $message->id,
            conversationId: $conversation->id,
            tenantId: tenant('id'),
            wabaId: $conversation->waba_id,
            deletedBy: [
                'type' => 'test',
                'user' => auth()->user()->name ?? 'Test User',
                'timestamp' => now()->toIso8601String(),
            ]
        ));

        return response()->json([
            'message' => 'MessageDeleted event dispatched successfully',
            'data' => [
                'message_id' => $message->id,
                'conversation_id' => $conversation->id,
                'status' => $message->status->value,
                'deleted_at' => $message->deleted_at,
                'event_channel' => 'tenant.'.tenant('id').'.waba.'.$conversation->waba_id.'.conversation',
                'event_name' => 'message.deleted',
            ],
        ]);
    }

    /**
     * Get a highlighted preview of the content with the search term
     */
    private function getHighlightedPreview(string $content, string $search, int $contextLength = 50): string
    {
        $position = stripos($content, $search);
        if ($position === false) {
            return $content;
        }

        $start = max(0, $position - $contextLength);
        $length = strlen($search) + ($contextLength * 2);

        $preview = substr($content, $start, $length);

        // Add ellipsis if needed
        if ($start > 0) {
            $preview = '...'.$preview;
        }
        if ($start + $length < strlen($content)) {
            $preview = $preview.'...';
        }

        return $preview;
    }
}
