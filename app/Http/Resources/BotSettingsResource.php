<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BotSettingsResource extends JsonResource
{
    public function toArray($request)
    {
        // Get tenant timezone
        $tenant = \App\Models\Tenant::current();
        $timezone = $tenant && $tenant->timezone ? $tenant->timezone->name : 'UTC';

        return [
            'id' => $this->id,
            'enable_working_hours' => $this->enable_working_hours,
            'working_hours' => $this->working_hours,
            'timezone' => $timezone,
            'out_of_office_bot_id' => $this->out_of_office_bot_id,
            'out_of_office_message' => $this->out_of_office_message,
            'default_no_match_action' => $this->default_no_match_action,
            'default_no_match_user_id' => $this->default_no_match_user_id,
            'default_no_match_bot_id' => $this->default_no_match_bot_id,
            'default_timeout_minutes' => $this->default_timeout_minutes,
            'default_timeout_action' => $this->default_timeout_action,
            'default_timeout_user_id' => $this->default_timeout_user_id,
            'default_timeout_bot_id' => $this->default_timeout_bot_id,
            'expire_warning_hours' => $this->expire_warning_hours,
            'default_expire_action' => $this->default_expire_action,
            'default_expire_message' => $this->default_expire_message,
            'default_expire_user_id' => $this->default_expire_user_id,
            'default_expire_bot_id' => $this->default_expire_bot_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
