<?php

namespace App\Jobs;

use App\Events\MentionNotification;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Http\Resources\UserResource;
use App\Models\Message;
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
        public string $messageId,
        public ?array $mentions, // Changed to accept the mentions object
        public string $tenantId,
        public string $wabaId
    ) {}

    public function handle(): void
    {
        tenancy()->initialize($this->tenantId);

        if (! $this->mentions || empty($this->mentions)) {
            return;
        }

        $message = Message::with(['conversation.contact', 'conversation.assignedUser'])
            ->find($this->messageId);

        if (! $message) {
            return;
        }

        $messageResource = MessageResource::make($message)->toArray(request());
        $conversationResource = ConversationResource::make($message->conversation)->toArray(request());

        // Extract mentioned users from the mentions array
        // Each element is an object like {"User Name": user_id}
        foreach ($this->mentions as $mention) {
            if (! is_array($mention)) {
                continue;
            }

            // Get the first (and only) key-value pair from the mention object
            $userName = array_key_first($mention);
            $userId = $mention[$userName];

            // Find the user by ID and verify the name matches
            $user = User::where('id', $userId)
                ->where('name', $userName)
                ->first();

            if (! $user) {
                continue;
            }

            $userResource = UserResource::make($user)->toArray(request());

            broadcast(new MentionNotification(
                message: $messageResource,
                mentionedUser: $userResource,
                conversation: $conversationResource,
                tenantId: $this->tenantId,
                wabaId: $this->wabaId,
                mentionedUserId: $user->id
            ));
        }
    }
}
