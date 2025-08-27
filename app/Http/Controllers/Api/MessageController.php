<?php

namespace App\Http\Controllers\Api;

use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Http\Controllers\Controller;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class MessageController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $input = $request->validate([
            'conversation_id' => ['required', 'exists:conversations,id'],
            'rows_per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $conversationId = data_get($input, 'conversation_id');
        $rowsPerPage = data_get($input, 'rows_per_page', 20);

        $messages = Message::query()
            ->where('conversation_id', $conversationId)
            ->orderBy('created_at', 'desc')
            ->paginate($rowsPerPage);

        return MessageResource::collection($messages);
    }

    public function store(Request $request): MessageResource
    {
        $input = $request->validate([
            'conversation_id' => ['required', 'exists:conversations,id'],
            'template_id' => ['nullable', 'exists:templates,id'],
            'reply_to_message_id' => ['nullable', 'exists:messages,id'],
            'direction' => ['required', 'in:'.implode(',', MessageDirection::values())],
            'type' => ['required', 'in:'.implode(',', MessageType::values())],
            'status' => ['nullable', 'in:'.implode(',', MessageStatus::values())],
            'content' => ['nullable', 'string'],
            'media' => ['nullable', 'array'],
            'from_phone' => ['nullable', 'string'],
            'to_phone' => ['nullable', 'string'],
        ]);

        $conversationId = data_get($input, 'conversation_id');
        $templateId = data_get($input, 'template_id');
        $replyToMessageId = data_get($input, 'reply_to_message_id');
        $direction = data_get($input, 'direction');
        $type = data_get($input, 'type');
        $status = data_get($input, 'status', MessageStatus::SENT->value);
        $content = data_get($input, 'content');
        $media = data_get($input, 'media');
        $fromPhone = data_get($input, 'from_phone');
        $toPhone = data_get($input, 'to_phone');

        $message = Message::create([
            'conversation_id' => $conversationId,
            'template_id' => $templateId,
            'reply_to_message_id' => $replyToMessageId,
            'direction' => $direction,
            'meta_id' => rand(),
            'type' => $type,
            'status' => $status,
            'content' => $content,
            'media' => $media,
            'from_phone' => $fromPhone,
            'to_phone' => $toPhone,
            'sent_at' => $direction === MessageDirection::OUTBOUND->value ? now() : null,
        ]);

        Conversation::where('id', $conversationId)->update([
            'last_message_at' => now(),
            'unread_count' => DB::raw('unread_count + 1'),
        ]);

        return new MessageResource($message);
    }
}
