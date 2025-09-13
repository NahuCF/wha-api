<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MentionNotification implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public array $message,
        public array $mentionedUser,
        public array $conversation,
        public string $tenantId,
        public string $wabaId,
        public string $mentionedUserId
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('tenant.'.$this->tenantId.'.waba.'.$this->wabaId.'.user.'.$this->mentionedUserId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.mention';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => $this->message,
            'mentioned_user' => $this->mentionedUser,
            'conversation' => $this->conversation,
        ];
    }
}
