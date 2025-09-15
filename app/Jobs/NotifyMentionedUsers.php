<?php

namespace App\Jobs;

use App\Events\MentionNotification;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifyMentionedUsers implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public array $message,
        public array $conversation,
        public array $userIds,
        public string $tenantId,
        public string $wabaId
    ) {}

    public function handle(): void
    {
        tenancy()->initialize($this->tenantId);

        foreach ($this->userIds as $userId) {
            $user = User::find($userId);

            broadcast(new MentionNotification(
                message: $this->message,
                conversation: $this->conversation,
                tenantId: $this->tenantId,
                wabaId: $this->wabaId,
                mentionedUserId: $user->id
            ));
        }
    }
}
