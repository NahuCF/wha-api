<?php

namespace App\Services\MessageSenders;

use App\Models\Message;
use App\Services\MetaService;
use App\Services\TemplateComponentBuilderService;

class TemplateSender implements MessageSenderInterface
{
    public function __construct(
        private MetaService $metaService,
        private TemplateComponentBuilderService $componentBuilder
    ) {}

    public function canHandle(Message $message): bool
    {
        return $message->template_id;
    }

    public function send(Message $message, string $phoneNumberId): array
    {
        $template = $message->template;

        // Use passed variables or fallback to message variables column
        $templateVariables = ! empty($variables) ? $variables : ($message->variables ?? []);

        $components = $this->componentBuilder->build($template, $templateVariables);

        return $this->metaService->sendTemplateMessage(
            phoneNumberId: $phoneNumberId,
            to: $message->to_phone,
            templateName: $template->name,
            language: $template->language,
            components: $components
        );
    }
}
