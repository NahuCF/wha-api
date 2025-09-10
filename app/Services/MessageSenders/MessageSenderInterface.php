<?php

namespace App\Services\MessageSenders;

use App\Models\Message;

interface MessageSenderInterface
{
    /**
     * Check if this sender can handle the message
     */
    public function canHandle(Message $message): bool;

    /**
     * Send the message
     */
    public function send(Message $message, string $phoneNumberId): array;
}
