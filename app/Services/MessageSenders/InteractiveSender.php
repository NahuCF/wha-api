<?php

namespace App\Services\MessageSenders;

use App\Models\Message;
use App\Services\MetaService;

class InteractiveSender implements MessageSenderInterface
{
    public function __construct(
        private MetaService $metaService
    ) {}

    public function canHandle(Message $message): bool
    {
        return ! empty($message->interactive_data);
    }

    public function send(Message $message, string $phoneNumberId): array
    {
        $interactiveData = $message->interactive_data;
        $type = $interactiveData['type'] ?? 'button';

        return $this->metaService->sendInteractiveMessage(
            phoneNumberId: $phoneNumberId,
            to: $message->to_phone,
            type: $type,
            interactive: $interactiveData
        );
    }
}
