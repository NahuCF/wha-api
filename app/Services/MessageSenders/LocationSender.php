<?php

namespace App\Services\MessageSenders;

use App\Models\Message;
use App\Services\MetaService;

class LocationSender implements MessageSenderInterface
{
    public function __construct(
        private MetaService $metaService
    ) {}

    public function canHandle(Message $message): bool
    {
        return ! empty($message->location_data);
    }

    public function send(Message $message, string $phoneNumberId): array
    {
        $locationData = $message->location_data;

        return $this->metaService->sendLocationMessage(
            phoneNumberId: $phoneNumberId,
            to: $message->to_phone,
            latitude: $locationData['latitude'],
            longitude: $locationData['longitude'],
            name: $locationData['name'] ?? null,
            address: $locationData['address'] ?? null
        );
    }
}
