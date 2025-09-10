<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Horizon Domain
    |--------------------------------------------------------------------------
    |
    | This is the subdomain where Horizon will be accessible from. If this
    | setting is null, Horizon will reside under the same domain as the
    | application. Otherwise, this value will serve as the subdomain.
    |
    */

    'domain' => env('HORIZON_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Path
    |--------------------------------------------------------------------------
    |
    | This is the URI path where Horizon will be accessible from. Feel free
    | to change this path to anything you like. Note that the URI will not
    | affect the paths of its internal API that aren't exposed to users.
    |
    */

    'path' => env('HORIZON_PATH', 'horizon'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Connection
    |--------------------------------------------------------------------------
    |
    | This is the name of the Redis connection where Horizon will store the
    | meta information required for it to function. It includes the list
    | of supervisors, failed jobs, job metrics, and other information.
    |
    */

    'use' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Prefix
    |--------------------------------------------------------------------------
    |
    | This prefix will be used when storing all Horizon data in Redis. You
    | may modify the prefix when you are running multiple installations
    | of Horizon on the same server so that they don't have problems.
    |
    */

    'prefix' => env(
        'HORIZON_PREFIX',
        Str::slug(env('APP_NAME', 'laravel'), '_').'_horizon:'
    ),

    /*
    |--------------------------------------------------------------------------
    | Horizon Route Middleware
    |--------------------------------------------------------------------------
    |
    | These middleware will get attached onto each Horizon route, giving you
    | the chance to add your own middleware to this list or change any of
    | the existing middleware. Or, you can simply stick with this list.
    |
    */

    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Queue Wait Time Thresholds
    |--------------------------------------------------------------------------
    |
    | This option allows you to configure when the LongWaitDetected event
    | will be fired. Every connection / queue combination may have its
    | own, unique threshold (in seconds) before this event is fired.
    |
    */

    'waits' => [
        'redis:emails' => 5,          // Alert if emails wait > 5 seconds
        'redis:critical' => 10,        // Alert if critical jobs wait > 10 seconds
        'redis:messages' => 30,        // Alert if messages wait > 30 seconds
        'redis:imports' => 60,         // Alert if imports wait > 60 seconds
        'redis:broadcasts' => 45,      // Alert if broadcasts wait > 45 seconds
        'redis:groups' => 60,          // Alert if group operations wait > 60 seconds
        'redis:default' => 60,         // Alert if default jobs wait > 60 seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Job Trimming Times
    |--------------------------------------------------------------------------
    |
    | Here you can configure for how long (in minutes) you desire Horizon to
    | persist the recent and failed jobs. Typically, recent jobs are kept
    | for one hour while all failed jobs are stored for an entire week.
    |
    */

    'trim' => [
        'recent' => 120,           // Keep recent for 2 hours
        'pending' => 120,          // Keep pending for 2 hours
        'completed' => 60,         // Keep completed for 1 hour
        'recent_failed' => 10080,  // Keep recent failed for 1 week
        'failed' => 10080,         // Keep failed for 1 week
        'monitored' => 10080,      // Keep monitored for 1 week
    ],

    /*
    |--------------------------------------------------------------------------
    | Silenced Jobs
    |--------------------------------------------------------------------------
    |
    | Silencing a job will instruct Horizon to not place the job in the list
    | of completed jobs within the Horizon dashboard. This setting may be
    | used to fully remove any noisy jobs from the completed jobs list.
    |
    */

    'silenced' => [
        // App\Jobs\ExampleJob::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Metrics
    |--------------------------------------------------------------------------
    |
    | Here you can configure how many snapshots should be kept to display in
    | the metrics graph. This will get used in combination with Horizon's
    | `horizon:snapshot` schedule to define how long to retain metrics.
    |
    */

    'metrics' => [
        'trim_snapshots' => [
            'job' => 48,    // Keep 48 hours of job metrics
            'queue' => 48,  // Keep 48 hours of queue metrics
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fast Termination
    |--------------------------------------------------------------------------
    |
    | When this option is enabled, Horizon's "terminate" command will not
    | wait on all of the workers to terminate unless the --wait option
    | is provided. Fast termination can shorten deployment delay by
    | allowing a new instance of Horizon to start while the last
    | instance will continue to terminate each of its workers.
    |
    */

    'fast_termination' => true,  // Enable for faster deployments

    /*
    |--------------------------------------------------------------------------
    | Memory Limit (MB)
    |--------------------------------------------------------------------------
    |
    | This value describes the maximum amount of memory the Horizon master
    | supervisor may consume before it is terminated and restarted. For
    | configuring these limits on your workers, see the next section.
    |
    */

    'memory_limit' => 256,

    /*
    |--------------------------------------------------------------------------
    | Queue Worker Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may define the queue worker settings used by your application
    | in all environments. These supervisors and settings handle all your
    | queued jobs and will be provisioned by Horizon during deployment.
    |
    | OPTIMIZED FOR: 8 cores / 16 threads @ 3.65 GHz
    | Total workers: ~12-14 to leave headroom for API and Soketi
    |
    */

    'defaults' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => ['default'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 1,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 1,
            'timeout' => 60,
            'nice' => 0,
        ],
    ],

    'environments' => [
        'production' => [
            'supervisor-emails' => [
                'connection' => 'redis',
                'queue' => ['emails'],
                'balance' => 'simple',
                'minProcesses' => 3,
                'maxProcesses' => 8,
                'balanceMaxShift' => 2,
                'balanceCooldown' => 3,
                'autoScalingStrategy' => 'time',
                'memory' => 256,
                'tries' => 3,
                'timeout' => 30,
                'nice' => -5,
            ],

            // HIGH PRIORITY: Critical operations (real-time messages, urgent tasks)
            'supervisor-critical' => [
                'connection' => 'redis',
                'queue' => ['fast'],
                'balance' => 'simple',
                'minProcesses' => 2,
                'maxProcesses' => 6,
                'balanceMaxShift' => 2,
                'balanceCooldown' => 3,
                'autoScalingStrategy' => 'time',
                'memory' => 512,
                'tries' => 3,
                'timeout' => 60,
                'nice' => -2,
            ],

            // MEDIUM-HIGH PRIORITY: WhatsApp messages (individual messages, not broadcasts)
            'supervisor-messages' => [
                'connection' => 'redis',
                'queue' => ['messages'],
                'balance' => 'auto',
                'minProcesses' => 3,
                'maxProcesses' => 8,
                'balanceMaxShift' => 2,
                'balanceCooldown' => 3,
                'autoScalingStrategy' => 'time',
                'memory' => 512,
                'tries' => 5,
                'timeout' => 120,
                'nice' => 0,
            ],

            // MEDIUM PRIORITY: Broadcast messages (bulk WhatsApp sends)
            'supervisor-broadcasts' => [
                'connection' => 'redis',
                'queue' => ['broadcasts'],
                'balance' => 'auto',
                'minProcesses' => 2,
                'maxProcesses' => 6,
                'balanceMaxShift' => 2,
                'balanceCooldown' => 5,
                'autoScalingStrategy' => 'size',  // Scale based on queue size
                'memory' => 1024,
                'tries' => 5,
                'timeout' => 1200,
                'nice' => 5,
                'sleep' => 1,
            ],

            // LOW PRIORITY: Contact imports (Excel processing)
            'supervisor-imports' => [
                'connection' => 'redis',
                'queue' => ['imports'],
                'balance' => 'auto',
                'minProcesses' => 2,
                'maxProcesses' => 5,
                'balanceMaxShift' => 2,
                'balanceCooldown' => 5,
                'autoScalingStrategy' => 'size',
                'memory' => 2048,
                'tries' => 2,
                'timeout' => 900,
                'nice' => 10,
                'sleep' => 5,
            ],

            // LOW PRIORITY: Group operations
            'supervisor-groups' => [
                'connection' => 'redis',
                'queue' => ['heavy'],
                'balance' => 'auto',
                'minProcesses' => 2,
                'maxProcesses' => 4,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 5,
                'autoScalingStrategy' => 'size',
                'memory' => 1024,
                'tries' => 3,
                'timeout' => 300,
                'nice' => 10,
                'sleep' => 3,
            ],

        ],

        'local' => [
            'supervisor-emails' => [
                'connection' => 'redis',
                'queue' => ['emails'],
                'balance' => 'simple',
                'processes' => 2,
                'memory' => 128,
                'tries' => 3,
                'timeout' => 30,
            ],

            'supervisor-critical' => [
                'connection' => 'redis',
                'queue' => ['critical', 'fast'],
                'balance' => 'simple',
                'processes' => 1,
                'memory' => 256,
                'tries' => 3,
                'timeout' => 60,
            ],

            'supervisor-messages' => [
                'connection' => 'redis',
                'queue' => ['messages'],
                'balance' => 'auto',
                'processes' => 2,
                'memory' => 256,
                'tries' => 5,
                'timeout' => 120,
            ],

            'supervisor-broadcasts' => [
                'connection' => 'redis',
                'queue' => ['broadcasts'],
                'balance' => 'auto',
                'processes' => 1,
                'memory' => 512,
                'tries' => 5,
                'timeout' => 600,
            ],

            'supervisor-imports' => [
                'connection' => 'redis',
                'queue' => ['imports'],
                'balance' => 'auto',
                'processes' => 1,
                'memory' => 512,
                'tries' => 2,
                'timeout' => 900,
            ],

            'supervisor-groups' => [
                'connection' => 'redis',
                'queue' => ['groups', 'heavy'],
                'balance' => 'auto',
                'processes' => 1,
                'memory' => 384,
                'tries' => 3,
                'timeout' => 300,
            ],

            'supervisor-default' => [
                'connection' => 'redis',
                'queue' => ['default'],
                'balance' => 'auto',
                'processes' => 1,
                'memory' => 256,
                'tries' => 3,
                'timeout' => 180,
            ],
        ],
    ],
];
