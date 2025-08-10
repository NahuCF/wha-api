<?php

namespace App\Services\MetaWebhook\Handlers;

use App\Models\Template;

class MessageTemplateStatusHandler extends HandlerInterface
{
    public function __construct(
    ) {}

    public function handle(array $data): void
    {
        $metaTemplateId = $data['message_template_id'];
        $status = $data['event'];
        $language = $data['message_template_language'];
        $reason = $data['reason'];

        $template = Template::query()
            ->where('meta_id', $metaTemplateId)
            ->first();

        if (! $template) {
            return;
        }

        $template
            ->update([
                'status' => $status,
                'reason' => $reason,
                'language' => $language,
            ]);
    }
}
