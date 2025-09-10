<?php

namespace App\Services\MessageSenders;

use App\Models\Message;
use App\Services\MetaService;

class TextSender implements MessageSenderInterface
{
    public function __construct(
        private MetaService $metaService
    ) {}

    public function canHandle(Message $message): bool
    {
        return ! empty($message->content);
    }

    public function send(Message $message, string $phoneNumberId): array
    {
        return $this->metaService->sendTextMessage(
            phoneNumberId: $phoneNumberId,
            to: $message->to_phone,
            text: $message->content
        );
    }
}
