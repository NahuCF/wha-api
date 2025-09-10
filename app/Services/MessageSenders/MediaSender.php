<?php

namespace App\Services\MessageSenders;

use App\Models\Message;
use App\Services\MetaService;

class MediaSender implements MessageSenderInterface
{
    public function __construct(
        private MetaService $metaService
    ) {}

    public function canHandle(Message $message): bool
    {
        return $message->media && ! empty($message->media);
    }

    public function send(Message $message, string $phoneNumberId): array
    {
        $media = $message->media[0];
        $mediaType = $media['type'] ?? 'image';

        // Call appropriate method based on media type
        return match ($mediaType) {
            'image' => $this->metaService->sendImageMessage(
                phoneNumberId: $phoneNumberId,
                to: $message->to_phone,
                link: $media['url'] ?? $media['link'],
                caption: $media['caption'] ?? null
            ),
            'video' => $this->metaService->sendVideoMessage(
                phoneNumberId: $phoneNumberId,
                to: $message->to_phone,
                link: $media['url'] ?? $media['link'],
                caption: $media['caption'] ?? null
            ),
            'audio' => $this->metaService->sendAudioMessage(
                phoneNumberId: $phoneNumberId,
                to: $message->to_phone,
                link: $media['url'] ?? $media['link']
            ),
            'document' => $this->metaService->sendDocumentMessage(
                phoneNumberId: $phoneNumberId,
                to: $message->to_phone,
                link: $media['url'] ?? $media['link'],
                caption: $media['caption'] ?? null,
                filename: $media['filename'] ?? null
            ),
            default => [
                'error' => "Unsupported media type: {$mediaType}",
            ]
        };
    }
}
