<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationDeleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly array $conversationIds,
        public readonly string $tenantId,
        public readonly string $wabaId,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('tenant.'.$this->tenantId.'.waba.'.$this->wabaId.'.conversation');
    }

    public function broadcastAs(): string
    {
        return 'conversation.deleted';
    }

    public function broadcastWith(): array
    {
        return [
            'converatation_ids' => $this->conversationIds,
        ];
    }
}
