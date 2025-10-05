<?php

namespace App\Jobs;

use App\Enums\MessageStatus;
use App\Events\MessageSent;
use App\Helpers\AppEnvironment;
use App\Models\Message;
use App\Services\MessageSenders\MessageSenderFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendWhatsAppMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $queue = 'messages';

    public $tries = 5;

    public $maxExceptions = 3;

    public $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private readonly array $messageData,
        private readonly string $tenantId,
        private readonly string $phoneNumberId,
        private readonly string $wabaId,
        private readonly string $conversationId,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(MessageSenderFactory $senderFactory): void
    {
        try {
            $message = Message::find($this->messageData['id']);

            if (isset($this->messageData['template_id'])) {
                $message->load('template');
            }

            $sender = $senderFactory->getSender($message);

            $response = AppEnvironment::isProduction()
                ? $sender->send($message, $this->phoneNumberId)
                : true;

            if ($response && ! isset($response['error'])) {
                $message->update([
                    'meta_id' => $response['messages'][0]['id'] ?? null,
                    'status' => MessageStatus::SENT->value,
                ]);

                $message->load(['conversation.contact']);

                broadcast(new MessageSent(
                    message: $message->toArray(),
                    conversationId: $this->conversationId,
                    tenantId: $this->tenantId,
                    wabaId: $this->wabaId
                ));

            } else {
                $error = $response['error'] ?? 'Unknown error';

                Message::where('id', $this->messageData['id'])->update([
                    'status' => MessageStatus::FAILED->value,
                    'failed_at' => now(),
                    'errors' => is_array($error) ? $error : ['error' => $error],
                ]);
            }
        } catch (Throwable $e) {
            if ($this->attempts() >= $this->tries) {
                Message::where('id', $this->messageData['id'])->update([
                    'status' => MessageStatus::FAILED->value,
                    'failed_at' => now(),
                    'errors' => ['error' => $e->getMessage()],
                ]);
            }

            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        $tenant = \App\Models\Tenant::find($this->tenantId);
        if ($tenant) {
            tenancy()->initialize($tenant);

            Message::where('id', $this->messageData['id'])->update([
                'status' => MessageStatus::FAILED->value,
                'failed_at' => now(),
                'errors' => [
                    'error' => $exception->getMessage(),
                    'attempts' => $this->attempts(),
                ],
            ]);
        }
    }

    public function backoff(): array
    {
        return [10, 30, 60, 120, 300]; // 10s, 30s, 1m, 2m, 5m
    }
}
