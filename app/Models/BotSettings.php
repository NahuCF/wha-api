<?php

namespace App\Models;

use App\Enums\BotAction;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotSettings extends Model
{
    use HasUlids;

    protected $fillable = [
        'tenant_id',
        'enable_working_hours',
        'working_hours',
        'out_of_office_bot_id',
        'out_of_office_message',
        'default_no_match_action',
        'default_no_match_user_id',
        'default_no_match_bot_id',
        'default_timeout_minutes',
        'default_timeout_action',
        'default_timeout_user_id',
        'default_timeout_bot_id',
        'expire_warning_hours',
        'default_expire_action',
        'default_expire_message',
        'default_expire_user_id',
        'default_expire_bot_id',
    ];

    protected $casts = [
        'enable_working_hours' => 'boolean',
        'working_hours' => 'array',
        'default_no_match_action' => BotAction::class,
        'default_timeout_action' => BotAction::class,
        'default_expire_action' => BotAction::class,
    ];

    public function outOfOfficeBot(): BelongsTo
    {
        return $this->belongsTo(Bot::class, 'out_of_office_bot_id');
    }

    public function defaultNoMatchUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'default_no_match_user_id');
    }

    public function defaultNoMatchBot(): BelongsTo
    {
        return $this->belongsTo(Bot::class, 'default_no_match_bot_id');
    }

    public function defaultTimeoutUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'default_timeout_user_id');
    }

    public function defaultTimeoutBot(): BelongsTo
    {
        return $this->belongsTo(Bot::class, 'default_timeout_bot_id');
    }

    public function defaultExpireUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'default_expire_user_id');
    }

    public function defaultExpireBot(): BelongsTo
    {
        return $this->belongsTo(Bot::class, 'default_expire_bot_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function isWithinWorkingHours(): bool
    {
        if (! $this->enable_working_hours) {
            return true;
        }

        // Get timezone from tenant
        $tenant = Tenant::find($this->tenant_id);
        $timezone = $tenant && $tenant->timezone ? $tenant->timezone->name : 'UTC';

        $now = Carbon::now($timezone);
        $dayOfWeek = strtolower($now->format('l'));

        $todayHours = $this->working_hours[$dayOfWeek] ?? null;

        if (! $todayHours || ! $todayHours['enabled']) {
            return false;
        }

        $currentTime = $now->format('H:i');
        $startTime = $todayHours['start'];
        $endTime = $todayHours['end'];

        return $currentTime >= $startTime && $currentTime <= $endTime;
    }
}
