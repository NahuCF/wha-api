<?php

namespace App\Console\Commands;

use App\Enums\BroadcastStatus;
use App\Jobs\ProcessBroadcast;
use App\Models\Broadcast;
use Illuminate\Console\Command;

class ProcessScheduledBroadcasts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'broadcasts:process-scheduled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process scheduled broadcasts that are ready to be sent';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Checking for scheduled broadcasts...');

        $scheduledBroadcasts = $this->getScheduledBroadcasts();
        $interruptedBroadcasts = $this->getInterruptedBroadcasts();

        $totalBroadcasts = $scheduledBroadcasts->count() + $interruptedBroadcasts->count();

        if ($totalBroadcasts === 0) {
            return Command::SUCCESS;
        }

        $this->info("Found {$totalBroadcasts} broadcast(s) to process");

        foreach ($scheduledBroadcasts as $broadcast) {
            ProcessBroadcast::dispatch($broadcast);
        }

        foreach ($interruptedBroadcasts as $broadcast) {
            $this->resumeInterruptedBroadcast($broadcast);
        }

        return Command::SUCCESS;
    }

    /**
     * Get all scheduled broadcasts ready to be sent
     */
    protected function getScheduledBroadcasts()
    {
        // Query broadcasts table directly since all tenants share the same database
        return Broadcast::with('tenant')
            ->where('status', BroadcastStatus::SCHEDULED)
            ->where('scheduled_at', '<=', now())
            ->get();
    }

    /**
     * Get all interrupted broadcasts that need to be resumed
     */
    protected function getInterruptedBroadcasts()
    {
        return Broadcast::with('tenant')
            ->whereIn('status', [BroadcastStatus::QUEUED, BroadcastStatus::SENDING])
            ->where('updated_at', '<', now()->subMinutes(15))
            ->get();
    }

    /**
     * Resume an interrupted broadcast
     */
    protected function resumeInterruptedBroadcast(Broadcast $broadcast): void
    {
        $this->warn("Resuming interrupted broadcast: {$broadcast->id} - {$broadcast->name}");

        $processedCount = $broadcast->messages()->count();
        $totalRecipients = $broadcast->total_recipients_count;

        if ($processedCount < $totalRecipients) {
            ProcessBroadcast::dispatch($broadcast);
        } else {
            $broadcast->update([
                'status' => BroadcastStatus::COMPLETED,
                'sent_at' => now(),
            ]);
        }

    }
}
