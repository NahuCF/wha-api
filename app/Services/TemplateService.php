<?php

namespace App\Services;

use App\Enums\BroadcastStatus;
use App\Models\Broadcast;

class TemplateService
{
    public function getActiveBroadcasts($template)
    {
        return Broadcast::query()
            ->where('template_id', $template->id)
            ->where('status', BroadcastStatus::SENDING)
            ->get();
    }
}
