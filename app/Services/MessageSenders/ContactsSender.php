<?php

namespace App\Services\MessageSenders;

use App\Models\Message;
use App\Services\MetaService;

class ContactsSender implements MessageSenderInterface
{
    public function __construct(
        private MetaService $metaService
    ) {}

    public function canHandle(Message $message): bool
    {
        return ! empty($message->contacts_data);
    }

    public function send(Message $message, string $phoneNumberId): array
    {
        return $this->metaService->sendContactsMessage(
            phoneNumberId: $phoneNumberId,
            to: $message->to_phone,
            contacts: $message->contacts_data
        );
    }
}
