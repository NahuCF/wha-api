<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class WhatsAppRateLimiter
{
    private const DEFAULT_RATE_LIMIT = 80;

    private const UPGRADED_RATE_LIMIT = 1000;

    private const WINDOW_SIZE = 1000;

    /**
     * Check if we can send a message and reserve a slot if available
     */
    public function attempt(string $phoneNumberId, int $count = 1, bool $isUpgraded = false): bool
    {
        $limit = $isUpgraded ? self::UPGRADED_RATE_LIMIT : self::DEFAULT_RATE_LIMIT;
        $key = $this->getCacheKey($phoneNumberId);
        $microtime = (int) (microtime(true) * 1000);

        // Clean old entries (older than 1 second)
        $this->cleanOldEntries($key, $microtime);

        // Get current count in the window
        $currentCount = $this->getCurrentCount($key);

        // Check if adding these messages would exceed the limit
        if ($currentCount + $count > $limit) {
            return false;
        }

        // Reserve slots for these messages
        $this->reserveSlots($key, $microtime, $count);

        return true;
    }

    /**
     * Wait until we can send messages
     */
    public function waitUntilAvailable(string $phoneNumberId, int $count = 1, bool $isUpgraded = false): int
    {
        $limit = $isUpgraded ? self::UPGRADED_RATE_LIMIT : self::DEFAULT_RATE_LIMIT;
        $key = $this->getCacheKey($phoneNumberId);
        $microtime = (int) (microtime(true) * 1000);

        $this->cleanOldEntries($key, $microtime);

        // Get current count
        $currentCount = $this->getCurrentCount($key);

        if ($currentCount + $count <= $limit) {
            return 0;
        }

        $timestamps = Cache::get($key, []);
        if (empty($timestamps)) {
            return 0;
        }

        sort($timestamps);

        // We need to wait until enough old messages expire
        $slotsNeeded = ($currentCount + $count) - $limit;
        if ($slotsNeeded > 0 && isset($timestamps[$slotsNeeded - 1])) {
            $oldestRelevantTime = $timestamps[$slotsNeeded - 1];
            $waitTime = ($oldestRelevantTime + self::WINDOW_SIZE) - $microtime;

            return max(0, $waitTime);
        }

        return 0;
    }

    /**
     * Get current throughput (messages sent in last second)
     */
    public function getCurrentThroughput(string $phoneNumberId): int
    {
        $key = $this->getCacheKey($phoneNumberId);
        $microtime = (int) (microtime(true) * 1000);

        $this->cleanOldEntries($key, $microtime);

        return $this->getCurrentCount($key);
    }

    /**
     * Reset rate limit for a phone number
     */
    public function reset(string $phoneNumberId): void
    {
        Cache::forget($this->getCacheKey($phoneNumberId));
    }

    /**
     * Get cache key for phone number
     */
    private function getCacheKey(string $phoneNumberId): string
    {
        return "whatsapp_rate_limit:{$phoneNumberId}";
    }

    /**
     * Clean entries older than the window size
     */
    private function cleanOldEntries(string $key, int $currentTime): void
    {
        $timestamps = Cache::get($key, []);

        $cutoff = $currentTime - self::WINDOW_SIZE;
        $timestamps = array_filter($timestamps, function ($timestamp) use ($cutoff) {
            return $timestamp > $cutoff;
        });

        // Update cache with cleaned data
        if (empty($timestamps)) {
            Cache::forget($key);
        } else {
            Cache::put($key, array_values($timestamps), 60); // Keep for 1 minute
        }
    }

    /**
     * Get current count of messages in the window
     */
    private function getCurrentCount(string $key): int
    {
        $timestamps = Cache::get($key, []);

        return count($timestamps);
    }

    /**
     * Reserve slots for messages
     */
    private function reserveSlots(string $key, int $timestamp, int $count): void
    {
        $timestamps = Cache::get($key, []);

        // Add new timestamps
        for ($i = 0; $i < $count; $i++) {
            $timestamps[] = $timestamp;
        }

        Cache::put($key, $timestamps, 60); // Keep for 1 minute
    }
}
