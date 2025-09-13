<?php

namespace App\Http\Controllers\Api;

use App\Enums\ConversationActivityType;
use App\Enums\MessageDirection;
use App\Enums\MessageSource;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Enums\TemplateStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Jobs\SendWhatsAppMessage;
use App\Models\Conversation;
use App\Models\ConversationActivity;
use App\Models\Message;
use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
            ->orderBy('created_at', 'asc')
            ->paginate($rowsPerPage);

        return MessageResource::collection($messages);
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

        $now = now();
        $nextDay = $now->addDays(1);
        $user = Auth::user();

        $conversation = Conversation::find($conversationId);

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
        if ($conversation->notStarted()) {
            $conversation->update([
                'last_message_at' => $now,
                'expires_at' => $nextDay,
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
            'to_phone' => $toPhone,
        ]);

        $message->load(['conversation.waba', 'conversation.phoneNumber', 'conversation.contact']);

        $phoneNumber = $message->conversation->phoneNumber;
        $waba = $message->conversation->waba;

        if (! $isTypeNote) {
            SendWhatsAppMessage::dispatch(
                messageData: $message->toArray(),
                tenantId: tenant('id'),
                phoneNumberId: $phoneNumber->meta_id,
                wabaId: $waba->id,
                conversationId: $conversation->id,
            )->onQueue('messages');
        }

        ConversationActivity::create([
            'tenant_id' => tenant('id'),
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'type' => ConversationActivityType::CONVERSATION_STARTED,
            'data' => ['user_name' => $user->name],
        ]);

        return MessageResource::make($message)->additional([
            'meta' => [
                'conversation' => ConversationResource::make($message->conversation),
            ],
        ]);
    }
}
