<?php

namespace App\Services\MetaWebhook\Handlers;

use App\Enums\ConversationActivityType;
use App\Enums\MessageDirection;
use App\Enums\MessageSource;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Events\ConversationNew;
use App\Events\MessageDeleted;
use App\Events\MessageDelivered;
use App\Events\MessageNew;
use App\Jobs\SendWhatsAppMessage;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\ConversationActivity;
use App\Models\Message;
use App\Models\Waba;
use App\Services\BotService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MessageHandler implements HandlerInterface
{
    public function handle(array $data): void
    {
        // Handle message status updates
        if (isset($data['statuses'])) {
            $this->handleStatusUpdates($data['statuses']);
        }

        // Handle incoming messages
        if (isset($data['messages'])) {
            $this->handleIncomingMessages($data);
        }
    }

    private function handleStatusUpdates(array $statuses): void
    {
        foreach ($statuses as $status) {
            $this->processStatusUpdate($status);
        }
    }

    private function processStatusUpdate(array $statusData): void
    {
        $metaMessageId = $statusData['id'] ?? null;
        $statusValue = $statusData['status'] ?? null;
        $timestamp = $statusData['timestamp'] ?? null;
        $errors = $statusData['errors'] ?? null;
        $conversationData = $statusData['conversation'] ?? null;

        $message = Message::where('meta_id', $metaMessageId)->first();

        $newStatus = $this->mapMetaStatusToEnum($statusValue);

        $currentStatus = $message->status;

        if ($currentStatus && $currentStatus->priority() >= $newStatus->priority()) {
            return;
        }

        $updateData = [
            'status' => $newStatus,
        ];

        if ($timestamp) {
            $timestampDate = \Carbon\Carbon::createFromTimestamp($timestamp);

            switch ($newStatus) {
                case MessageStatus::SENT:
                    $updateData['sent_at'] = $timestampDate;
                    break;
                case MessageStatus::DELIVERED:
                    $updateData['delivered_at'] = $timestampDate;
                    break;
                case MessageStatus::READ:
                    $updateData['read_at'] = $timestampDate;
                    break;
                case MessageStatus::FAILED:
                    $updateData['failed_at'] = $timestampDate;
                    break;
                case MessageStatus::DELETED:
                    $updateData['deleted_at'] = $timestampDate;
                    break;
            }
        }

        if ($errors) {
            $updateData['errors'] = $errors;
        }

        $message->update($updateData);

        // Update conversation expiration if provided
        // Only for status sent
        if ($conversationData && isset($conversationData['expiration_timestamp'])) {
            $conversation = $message->conversation;
            if ($conversation) {
                $expiresAt = \Carbon\Carbon::createFromTimestamp($conversationData['expiration_timestamp']);
                $conversation->update(['expires_at' => $expiresAt]);
            }
        }

        // Broadcast message status update
        if ($message && $message->conversation) {
            $conversation = $message->conversation;
            $conversation->load(['contact', 'assignedUser', 'latestMessage', 'waba', 'phoneNumber']);

            // Broadcast deleted message event separately
            if ($newStatus === MessageStatus::DELETED) {
                broadcast(new MessageDeleted(
                    messageId: $message->id,
                    conversationId: $conversation->id,
                    tenantId: $message->tenant_id,
                    wabaId: $conversation->waba_id,
                    deletedBy: [
                        'type' => 'user',
                        'phone' => $message->direction === MessageDirection::OUTBOUND ? $message->to_phone : null,
                    ]
                ));
            } else {
                broadcast(new MessageDelivered(
                    messageId: $message->id,
                    conversationId: $conversation->id,
                    tenantId: $message->tenant_id,
                    wabaId: $conversation->waba_id,
                    status: $newStatus->value
                ));
            }
        }
    }

    private function handleIncomingMessages(array $data): void
    {
        $messages = $data['messages'] ?? [];
        $metadata = $data['metadata'] ?? [];
        $contacts = $data['contacts'] ?? [];

        $phoneNumberId = $metadata['phone_number_id'] ?? null;
        $displayPhoneNumber = $metadata['display_phone_number'] ?? null;

        $waba = Waba::whereHas('phoneNumbers', function ($query) use ($phoneNumberId) {
            $query->where('meta_id', $phoneNumberId);
        })->first();

        foreach ($messages as $messageData) {
            DB::transaction(function () use ($messageData, $waba, $contacts, $phoneNumberId, $displayPhoneNumber) {
                $this->processIncomingMessage($messageData, $waba, $contacts, $phoneNumberId, $displayPhoneNumber);
            });
        }
    }

    private function processIncomingMessage(array $messageData, Waba $waba, array $contacts, string $phoneNumberId, ?string $displayPhoneNumber): void
    {
        $from = $messageData['from'] ?? null;
        $metaId = $messageData['id'] ?? null;
        $timestamp = $messageData['timestamp'] ?? null;
        $type = $messageData['type'] ?? null;
        $context = $messageData['context'] ?? null;

        // Find or create contact
        $contactInfo = $this->findContactInfo($from, $contacts);
        $contact = $this->findOrCreateContact($from, $contactInfo, $waba);

        $conversation = $this->findOrCreateConversation($contact, $waba, $from, $phoneNumberId);

        $message = new Message([
            'tenant_id' => $waba->tenant_id,
            'conversation_id' => $conversation->id,
            'meta_id' => $metaId,
            'contact_id' => $contact->id,
            'direction' => MessageDirection::INBOUND,
            'type' => MessageType::from($type),
            'status' => MessageStatus::DELIVERED,
            'to_phone' => $displayPhoneNumber,
            'delivered_at' => $timestamp ? \Carbon\Carbon::createFromTimestamp($timestamp) : now(),
        ]);

        broadcast(new MessageNew(
            message: $message->toArray(),
            conversation: $conversation->toArray(),
            tenantId: $waba->tenant_id,
            wabaId: $waba->id
        ));

        // Handle reply context
        if ($context && isset($context['id'])) {
            $replyToMessage = Message::where('meta_id', $context['id'])->first();
            if ($replyToMessage) {
                $message->reply_to_message_id = $replyToMessage->id;
            }
        }

        // Process message content based on type
        $this->processMessageContent($message, $messageData, $type);

        $message->save();

        $updateData = [
            'last_message_at' => $message->delivered_at,
            'unread_count' => $conversation->unread_count + 1,
            'expires_at' => $message->delivered_at->addHours(24),
        ];

        if (! $conversation->started_at) {
            $updateData['started_at'] = $message->delivered_at;
        }

        $conversation->update($updateData);

        // Check tenant working hours using WABA's tenant_id
        $tenantSettings = \App\Models\TenantSettings::where('tenant_id', $waba->tenant_id)->first();

        if ($tenantSettings && ! $tenantSettings->isWithinWorkingHours()) {
            // Send away message if configured
            $awayMessage = $tenantSettings->getAwayMessage();
            if ($awayMessage) {
                // Send automated away message
                $awayReply = Message::create([
                    'tenant_id' => $waba->tenant_id,
                    'conversation_id' => $conversation->id,
                    'contact_id' => $contact->id,
                    'direction' => MessageDirection::OUTBOUND,
                    'type' => MessageType::TEXT,
                    'status' => MessageStatus::PENDING,
                    'source' => MessageSource::BOT,
                    'content' => $awayMessage,
                    'to_phone' => $contact->phone,
                ]);

                $conversation->update(['last_message_at' => now()]);

                // Send the message via WhatsApp
                $phoneNumber = $conversation->phoneNumber;
                SendWhatsAppMessage::dispatch(
                    messageData: $awayReply->toArray(),
                    tenantId: $conversation->tenant_id,
                    phoneNumberId: $phoneNumber->meta_id,
                    wabaId: $waba->id,
                    conversationId: $conversation->id,
                );
            }

            // Don't process bot logic if outside working hours
            return;
        }

        // Handle bot logic for incoming messages
        $botService = new BotService;
        $botService->handleIncomingMessage($message, $conversation, $contact);
    }

    private function processMessageContent(Message $message, array $messageData, string $type): void
    {
        $messageType = MessageType::tryFrom($type);

        switch ($messageType) {
            case MessageType::TEXT:
                $message->content = $messageData['text']['body'] ?? null;
                break;

            case MessageType::IMAGE:
            case MessageType::VIDEO:
            case MessageType::AUDIO:
            case MessageType::DOCUMENT:
            case MessageType::STICKER:
                $media = $messageData[$type] ?? [];
                $message->media = [
                    'id' => $media['id'] ?? null,
                    'mime_type' => $media['mime_type'] ?? null,
                    'sha256' => $media['sha256'] ?? null,
                    'filename' => $media['filename'] ?? null,
                    'caption' => $media['caption'] ?? null,
                ];
                $message->content = $media['caption'] ?? null;
                break;

            case MessageType::LOCATION:
                $location = $messageData['location'] ?? [];
                $message->location_data = [
                    'latitude' => $location['latitude'] ?? null,
                    'longitude' => $location['longitude'] ?? null,
                    'name' => $location['name'] ?? null,
                    'address' => $location['address'] ?? null,
                ];
                break;

            case MessageType::CONTACTS:
                $message->contacts_data = $messageData['contacts'] ?? [];
                break;

            case MessageType::INTERACTIVE:
                $interactive = $messageData['interactive'] ?? [];
                $message->interactive_data = $interactive;

                // Extract response - bot needs ID, display needs title
                if ($interactive['type'] === 'button_reply') {
                    // For bot processing, use ID; for display, use title
                    $message->content = $interactive['button_reply']['id'] ?? null;
                    $message->display_content = $interactive['button_reply']['title'] ?? null;
                } elseif ($interactive['type'] === 'list_reply') {
                    $message->content = $interactive['list_reply']['id'] ?? null;
                    $message->display_content = $interactive['list_reply']['title'] ?? null;
                }
                break;

            case MessageType::BUTTON:
                $button = $messageData['button'] ?? [];
                $message->interactive_data = ['button' => $button];
                $message->content = $button['text'] ?? null;
                $message->display_content = $button['text'] ?? null;
                break;

            case MessageType::REACTION:
                $reaction = $messageData['reaction'] ?? [];
                $message->content = $reaction['emoji'] ?? null;
                $message->context = [
                    'reaction_to' => $reaction['message_id'] ?? null,
                ];
                break;

            case MessageType::ORDER:
                $message->context = [
                    'order' => $messageData['order'] ?? [],
                ];
                break;

            default:
                Log::warning('MessageHandler: Unhandled message type', ['type' => $type]);
                $message->context = $messageData;
        }
    }

    private function findContactInfo(string $phone, array $contacts): ?array
    {
        foreach ($contacts as $contact) {
            if ($contact['wa_id'] === $phone) {
                return $contact['profile'] ?? null;
            }
        }

        return null;
    }

    private function findOrCreateContact(string $phone, ?array $profile, Waba $waba): Contact
    {
        $contact = Contact::where('tenant_id', $waba->tenant_id)
            ->where('phone', $phone)
            ->first();

        if (! $contact) {
            $contact = Contact::create([
                'tenant_id' => $waba->tenant_id,
                'phone' => $phone,
                'name' => $profile['name'] ?? $phone,
                'meta_name' => $profile['name'] ?? null,
            ]);
        } elseif ($profile && isset($profile['name']) && $contact->meta_name !== $profile['name']) {
            $contact->update(['meta_name' => $profile['name']]);
        }

        return $contact;
    }

    private function findOrCreateConversation(Contact $contact, Waba $waba, string $phone, string $phoneNumberId): Conversation
    {
        $conversation = Conversation::where('tenant_id', $waba->tenant_id)
            ->where('waba_id', $waba->id)
            ->where('contact_id', $contact->id)
            ->first();

        if (! $conversation) {
            $conversation = Conversation::create([
                'tenant_id' => $waba->tenant_id,
                'waba_id' => $waba->id,
                'contact_id' => $contact->id,
                'contact_phone' => $phone,
                'waba_phone_id' => $phoneNumberId,
                'last_message_at' => now(),
                'expires_at' => now()->addDays(1),
                'started_at' => now(),
            ]);

            // Create activity for new conversation created by incoming message
            ConversationActivity::create([
                'tenant_id' => $waba->tenant_id,
                'conversation_id' => $conversation->id,
                'user_id' => null,
                'type' => ConversationActivityType::CONVERSATION_STARTED,
                'data' => [
                    'contact_name' => $contact->name ?? $phone,
                ],
            ]);

            broadcast(new ConversationNew(
                conversation: $conversation,
                tenantId: $waba->tenant_id,
                wabaId: $waba->id
            ));
        }

        return $conversation;
    }

    private function mapMetaStatusToEnum(string $metaStatus): ?MessageStatus
    {
        return match (strtolower($metaStatus)) {
            'sent' => MessageStatus::SENT,
            'delivered' => MessageStatus::DELIVERED,
            'read' => MessageStatus::READ,
            'failed' => MessageStatus::FAILED,
            'deleted' => MessageStatus::DELETED,
            default => null,
        };
    }
}
