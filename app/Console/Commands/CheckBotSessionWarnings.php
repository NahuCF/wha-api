<?php

namespace App\Console\Commands;

use App\Enums\BotSessionStatus;
use App\Jobs\ProcessBotSessionWarning;
use App\Models\BotSession;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckBotSessionWarnings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bot:check-warnings';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for bot sessions about to expire and dispatch warning jobs';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $sessionsToWarn = BotSession::query()
            ->whereIn('status', [BotSessionStatus::ACTIVE, BotSessionStatus::WAITING])
            ->whereNull('warning_sent_at')
            ->join('bots', 'bot_sessions.bot_id', '=', 'bots.id')
            ->whereNotNull('bots.about_to_end_time_minutes')
            ->whereNotNull('bots.about_to_end_message')
            ->whereRaw('bot_sessions.timeout_at <= NOW() + INTERVAL \'1 minute\' * bots.about_to_end_time_minutes')
            ->whereRaw('bot_sessions.timeout_at > NOW()')
            ->select('bot_sessions.id', 'bot_sessions.tenant_id')
            ->limit(500) 
            ->get();

        $count = $sessionsToWarn->count();
        
        if ($count === 0) {
            return;
        }

        $sessionsByTenant = $sessionsToWarn->groupBy('tenant_id');

        foreach ($sessionsByTenant as $tenantId => $sessions) {
            foreach ($sessions as $session) {
                ProcessBotSessionWarning::dispatch(
                    $session->id,
                    $tenantId
                )->onQueue('bot-warnings');
            }
        }
    }
}