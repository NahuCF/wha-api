<?php

namespace App\Services;

use App\Enums\BroadcastStatus;
use App\Models\Broadcast;
use App\Models\Template;

class TemplateService
{
    public function getActiveBroadcasts($template)
    {
        return Broadcast::query()
            ->where('template_id', $template->id)
            ->where('status', BroadcastStatus::SENDING)
            ->get();
    }

    public function templateComponentsToMeta(Template $template)
    {
        $components = [];

        if ($template->header) {
            $components[] = [
                'type' => 'HEADER',
                'format' => 'TEXT',
                'text' => $template->header,
            ];
        }

        if ($template->body) {
            $bodyComponent = [
                'type' => 'BODY',
                'text' => $template->body,
            ];
            // if($template->body_example_variables) {

            // $bodyComponent['exampop'] = $template->body_example_variables;
            // }

            $component[] = $bodyComponent;
        }

        if ($template->footer) {
            $components[] = [
                'type' => 'FOOTER',
                'text' => $template->footer,
            ];
        }

        return $components;
    }
}
