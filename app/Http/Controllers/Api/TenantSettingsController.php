<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TenantSettingsResource;
use App\Models\TenantSettings;
use Illuminate\Http\Request;

class TenantSettingsController extends Controller
{
    public function show()
    {
        $tenantId = tenant()->id;
        $settings = TenantSettings::firstOrCreate(
            ['tenant_id' => $tenantId],
            [
                'timezone' => 'UTC',
            ]
        );

        return TenantSettingsResource::make($settings);
    }

    public function update(Request $request)
    {
        $input = $request->validate([
            'timezone' => ['sometimes', 'string'],

            'working_days' => ['nullable', 'array'],
            'working_days.monday' => ['sometimes', 'array'],
            'working_days.monday.*.start' => ['required', 'date_format:H:i'],
            'working_days.monday.*.end' => ['required', 'date_format:H:i'],
            'working_days.tuesday' => ['sometimes', 'array'],
            'working_days.tuesday.*.start' => ['required', 'date_format:H:i'],
            'working_days.tuesday.*.end' => ['required', 'date_format:H:i'],
            'working_days.wednesday' => ['sometimes', 'array'],
            'working_days.wednesday.*.start' => ['required', 'date_format:H:i'],
            'working_days.wednesday.*.end' => ['required', 'date_format:H:i'],
            'working_days.thursday' => ['sometimes', 'array'],
            'working_days.thursday.*.start' => ['required', 'date_format:H:i'],
            'working_days.thursday.*.end' => ['required', 'date_format:H:i'],
            'working_days.friday' => ['sometimes', 'array'],
            'working_days.friday.*.start' => ['required', 'date_format:H:i'],
            'working_days.friday.*.end' => ['required', 'date_format:H:i'],
            'working_days.saturday' => ['sometimes', 'array'],
            'working_days.saturday.*.start' => ['required', 'date_format:H:i'],
            'working_days.saturday.*.end' => ['required', 'date_format:H:i'],
            'working_days.sunday' => ['sometimes', 'array'],
            'working_days.sunday.*.start' => ['required', 'date_format:H:i'],
            'working_days.sunday.*.end' => ['required', 'date_format:H:i'],

            'special_days' => ['nullable', 'array'],
            'special_days.*.closed' => ['sometimes', 'boolean'],
            'special_days.*.*.start' => ['sometimes', 'date_format:H:i'],
            'special_days.*.*.end' => ['sometimes', 'date_format:H:i'],

            'closed_days' => ['nullable', 'array'],
            'closed_days.*.closed' => ['sometimes', 'boolean'],
            'closed_days.*.*.start' => ['sometimes', 'date_format:H:i'],
            'closed_days.*.*.end' => ['sometimes', 'date_format:H:i'],

            'away_message' => ['nullable', 'string', 'max:1000'],
        ]);

        $timezone = data_get($input, 'timezone');
        $workingDays = data_get($input, 'working_days');
        $specialDays = data_get($input, 'special_days');
        $closedDays = data_get($input, 'closed_days');
        $awayMessage = data_get($input, 'away_message');

        $tenantId = tenant()->id;
        $settings = TenantSettings::updateOrCreate(
            ['tenant_id' => $tenantId],
            [
                'timezone' => $timezone,
                'working_days' => $workingDays,
                'special_days' => $specialDays,
                'closed_days' => $closedDays,
                'away_message' => $awayMessage,
            ]
        );

        return TenantSettingsResource::make($settings);
    }
}
