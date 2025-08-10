<?php

namespace App\Services;

use App\Services\MetaWebhook\Handlers\HandlerInterface;
use App\Services\MetaWebhook\Handlers\MessageTemplateStatusHandler;

class MetaWebhookService
{
    private array $handlers = [
        'message_template_status_update' => MessageTemplateStatusHandler::class,
    ];

    public function process(string $field, array $value): void
    {
        $handler = $this->getHandler($field);

        if ($handler) {
            $handler->handle($value);
        } else {
            $this->handleUnknownEvent($field);
        }
    }

    private function getHandler(string $field): ?HandlerInterface
    {
        if (! isset($this->handlers[$field])) {
            return null;
        }

        return app($this->handlers[$field]);
    }

    private function handleUnknownEvent(string $field): void
    {
        throw new \Exception('Unknown event received: '.$field);
    }
}
