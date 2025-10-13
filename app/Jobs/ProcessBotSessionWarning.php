<?php

namespace App\Jobs;

use App\Models\BotSession;
use App\Services\BotService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ProcessBotSessionWarning implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;


    public $tries = 3;

    
    public $maxExceptions = 3;

    public function __construct(
        protected string $sessionId,
        protected string $tenantId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        tenancy()->initialize($this->tenantId);

        DB::transaction(function () {
            $session = BotSession::where('id', $this->sessionId)
                ->whereNull('warning_sent_at')
                ->with(['bot', 'conversation', 'contact'])
                ->lockForUpdate()
                ->first();

            if (!$session) {
                return;
            }

            if (!in_array($session->status->value ?? $session->status, ['active', 'waiting'])) {
                return;
            }

            if ($session->timeout_at && $session->timeout_at <= now()) {
                return;
            }

            $botService = new BotService();
            $botService->handleAboutToEnd($session);

            $session->update(['warning_sent_at' => now()]);
        });
    }


}