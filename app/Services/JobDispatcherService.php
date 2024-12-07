<?php

namespace App\Services;

use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

class JobDispatcherService
{
    public static function dispatch($job, $queue = null)
    {
        try {
            Redis::connection()->ping();

            return Queue::connection('redis')->push($job, [], $queue);
        } catch (\Exception $e) {
            return Queue::connection('database')->push($job, [], $queue);
        }
    }
}
