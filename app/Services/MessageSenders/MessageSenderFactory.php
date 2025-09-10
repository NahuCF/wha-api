<?php

namespace App\Services\MessageSenders;

use App\Models\Message;
use App\Services\MetaService;
use App\Services\TemplateComponentBuilderService;
use RuntimeException;

class MessageSenderFactory
{
    private array $senders = [];

    public function __construct(
        MetaService $metaService,
        TemplateComponentBuilderService $componentBuilder
    ) {
        // Register all available message senders
        // Order matters: more specific senders should come first
        $this->senders = [
            new TemplateSender($metaService, $componentBuilder),
            new MediaSender($metaService),
            new LocationSender($metaService),
            new ContactsSender($metaService),
            new InteractiveSender($metaService),
            new TextSender($metaService), // Text sender should be last as it's the most generic
        ];
    }

    /**
     * Get the appropriate sender for the message
     *
     * @throws RuntimeException if no sender can handle the message
     */
    public function getSender(Message $message): MessageSenderInterface
    {
        foreach ($this->senders as $sender) {
            if ($sender->canHandle($message)) {
                return $sender;
            }
        }

        throw new RuntimeException(
            'No message sender available for message type: '.$message->type?->value
        );
    }

    /**
     * Register a custom sender
     */
    public function registerSender(MessageSenderInterface $sender): void
    {
        array_unshift($this->senders, $sender);
    }
}
