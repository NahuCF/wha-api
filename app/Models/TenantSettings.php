<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantSettings extends Model
{
    use HasUlids;

    protected $casts = [
        'working_days' => 'array',
        'special_days' => 'array',
        'closed_days' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * Check if current time is within working hours
     */
    public function isWithinWorkingHours(): bool
    {
        // If no working days configured, we're 24/7
        if (empty($this->working_days)) {
            return true;
        }

        $now = Carbon::now($this->timezone);
        $today = $now->format('Y-m-d');
        $dayOfWeek = strtolower($now->format('l'));
        $currentTime = $now->format('H:i');

        if ($this->closed_days && isset($this->closed_days[$today])) {
            $closedDay = $this->closed_days[$today];
            if (is_array($closedDay) && isset($closedDay['closed']) && $closedDay['closed']) {
                return false;
            }
        }

        if ($this->special_days && isset($this->special_days[$today])) {
            $specialDay = $this->special_days[$today];

            if (is_array($specialDay) && isset($specialDay['closed']) && $specialDay['closed']) {
                return false;
            }

            if (is_array($specialDay) && ! isset($specialDay['closed'])) {
                $ranges = isset($specialDay[0]) ? $specialDay : [$specialDay];
                foreach ($ranges as $range) {
                    if (isset($range['start']) && isset($range['end'])) {
                        if ($currentTime >= $range['start'] && $currentTime <= $range['end']) {
                            return true;
                        }
                    }
                }

                return false;
            }
        }

        // Check regular working days
        if (isset($this->working_days[$dayOfWeek])) {
            $dayRanges = $this->working_days[$dayOfWeek];

            if (isset($dayRanges['start']) && isset($dayRanges['end'])) {
                return $currentTime >= $dayRanges['start'] && $currentTime <= $dayRanges['end'];
            } elseif (is_array($dayRanges)) {
                foreach ($dayRanges as $range) {
                    if (isset($range['start']) && isset($range['end'])) {
                        if ($currentTime >= $range['start'] && $currentTime <= $range['end']) {
                            return true;
                        }
                    }
                }
            }
        }

        // Day not configured = closed
        return false;
    }

    /**
     * Get the away message if configured
     */
    public function getAwayMessage(): ?string
    {
        // Return away message if set, otherwise null
        return $this->away_message;
    }
}
