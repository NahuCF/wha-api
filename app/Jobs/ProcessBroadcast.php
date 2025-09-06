<?php

namespace App\Jobs;

use App\Enums\BroadcastStatus;
use App\Enums\MessageDirection;
use App\Enums\MessageSource;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Helpers\AppEnvironment;
use App\Models\Broadcast;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\MetaService;
use App\Services\TemplateComponentBuilderService;
use App\Services\WhatsAppRateLimiter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessBroadcast implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Broadcast $broadcast;

    protected int $batchSize;

    protected int $offset;

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 5;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     */
    public $maxExceptions = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public $timeout = 600; // 10 minutes per batch

    /**
     * Create a new job instance.
     */
    public function __construct(Broadcast $broadcast, int $batchSize = 500, int $offset = 0)
    {
        $this->broadcast = $broadcast;
        $this->batchSize = $batchSize;
        $this->offset = $offset;
    }

    /**
     * Execute the job.
     */
    public function handle(MetaService $metaService, TemplateComponentBuilderService $templateBuilder, WhatsAppRateLimiter $rateLimiter): void
    {
        tenancy()->initialize($this->broadcast->tenant);

        try {
            // Update  status to sending if not already
            if ($this->broadcast->status !== BroadcastStatus::SENDING) {
                $this->broadcast->update(['status' => BroadcastStatus::SENDING]);
            }

            $allContactIds = $this->getContactIds();
            $processedContactIds = $this->getProcessedContactIds();
            $remainingContactIds = $allContactIds->diff($processedContactIds);

            if ($remainingContactIds->isEmpty()) {
                $this->markBroadcastAsCompleted();

                return;
            }

            // Process batch of contacts
            $contactBatch = $remainingContactIds->slice($this->offset, $this->batchSize);

            if ($contactBatch->isEmpty()) {
                $this->markBroadcastAsCompleted();

                return;
            }

            $contacts = $this->preloadContactData($contactBatch);

            // Skip if no contacts were found (e.g., they were deleted)
            if ($contacts->isEmpty()) {
                // Dispatch next batch if there are more
                if ($remainingContactIds->count() > $contactBatch->count()) {
                    $nextOffset = $this->offset + $this->batchSize;
                    self::dispatch($this->broadcast, $this->batchSize, $nextOffset)
                        ->delay(now()->addSeconds(1));
                }

                return;
            }

            $conversations = $this->bulkGetOrCreateConversations($contacts);

            $messagesToInsert = [];
            $now = now();

            foreach ($contacts as $contact) {
                $phoneNumbers = $this->getContactPhoneNumbers($contact);

                // If send_to_all_numbers is false, only use the first phone number
                if (! $this->broadcast->send_to_all_numbers) {
                    $phoneNumbers = array_slice($phoneNumbers, 0, 1);
                }

                foreach ($phoneNumbers as $phoneNumber) {
                    // Get conversation for this specific phone number
                    $conversation = $conversations[$contact->id.'_'.$phoneNumber];

                    $messagesToInsert[] = [
                        'id' => \Illuminate\Support\Str::ulid(),
                        'tenant_id' => $this->broadcast->tenant_id,
                        'conversation_id' => $conversation->id,
                        'broadcast_id' => $this->broadcast->id,
                        'template_id' => $this->broadcast->template_id,
                        'direction' => MessageDirection::OUTBOUND->value,
                        'type' => MessageType::TEMPLATE->value,
                        'status' => MessageStatus::PENDING->value,
                        'source' => MessageSource::BROADCAST->value,
                        'content' => $this->buildMessageContent($contact, $templateBuilder),
                        'from_phone' => $this->broadcast->phoneNumber->phone_number ?? null,
                        'to_phone' => $phoneNumber,
                        'meta_id' => uniqid('broadcast_', true),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            if (! empty($messagesToInsert)) {
                DB::table('messages')->insert($messagesToInsert);

                $this->broadcast->increment('sent_count', count($messagesToInsert));
                $this->broadcast->touch();
            }

            if (AppEnvironment::isProduction()) {
                $this->sendMessagesToWhatsApp($messagesToInsert, $contacts, $metaService, $templateBuilder, $rateLimiter);
                $this->broadcast->touch();
            } else {
                $messageIds = array_column($messagesToInsert, 'id');
                Message::whereIn('id', $messageIds)->update([
                    'status' => MessageStatus::SENT->value,
                    'sent_at' => $now,
                ]);
            }

            // Check if there are more contacts to process
            $totalRemaining = $remainingContactIds->count();
            $processedInThisBatch = $contactBatch->count();

            if ($totalRemaining > $processedInThisBatch) {
                // Dispatch next batch with a small delay to avoid overwhelming the system
                $nextOffset = $this->offset + $this->batchSize;
                self::dispatch($this->broadcast, $this->batchSize, $nextOffset)
                    ->delay(now()->addSeconds(1));
            } else {
                $this->markBroadcastAsCompleted();
            }

        } catch (Throwable $e) {
            // Only mark as failed if we've exceeded max retries
            if ($this->attempts() >= $this->tries) {
                $this->broadcast->update([
                    'status' => BroadcastStatus::FAILED,
                ]);
            }

            throw $e;
        }
    }

    protected function getContactIds(): Collection
    {
        $contactIds = collect($this->broadcast->recipients ?? []);

        // Get contact IDs from groups - fresh data every time
        $groupContactIds = DB::table('contact_group')
            ->whereIn('group_id', $this->broadcast->groups()->pluck('id'))
            ->pluck('contact_id');

        return $contactIds->merge($groupContactIds)->unique()->values();
    }

    /**
     * Get already processed contact IDs
     */
    protected function getProcessedContactIds(): Collection
    {
        $processedIds = collect();

        DB::table('messages')
            ->select('conversations.contact_id')
            ->join('conversations', 'messages.conversation_id', '=', 'conversations.id')
            ->where('messages.broadcast_id', $this->broadcast->id)
            ->whereNotNull('conversations.contact_id')
            ->distinct()
            ->orderBy('conversations.contact_id')
            ->chunk(1000, function ($contacts) use (&$processedIds) {
                $processedIds = $processedIds->merge($contacts->pluck('contact_id'));
            });

        return $processedIds->unique();
    }

    protected function preloadContactData(Collection $contactIds): Collection
    {
        return Contact::whereIn('id', $contactIds)
            ->with(['fieldValues' => function ($query) {
                $query->with('field');
            }])
            ->get()
            ->keyBy('id');
    }

    /**
     * Bulk get or create conversations for all contact phone numbers
     */
    protected function bulkGetOrCreateConversations(Collection $contacts): array
    {
        $conversations = [];
        $wabaId = $this->broadcast->phoneNumber->waba_id ?? null;

        // Collect all phone numbers from all contacts
        $contactPhoneMap = [];
        foreach ($contacts as $contact) {
            $phoneNumbers = $this->getContactPhoneNumbers($contact);

            // If send_to_all_numbers is false, only use the first phone number
            if (! $this->broadcast->send_to_all_numbers) {
                $phoneNumbers = array_slice($phoneNumbers, 0, 1);
            }

            foreach ($phoneNumbers as $phone) {
                $contactPhoneMap[] = [
                    'contact_id' => $contact->id,
                    'phone' => $phone,
                    'key' => $contact->id.'_'.$phone,
                ];
            }
        }

        if (empty($contactPhoneMap)) {
            return $conversations;
        }

        // Get existing conversations for specific contact-phone combinations
        $existingConversations = Conversation::where('waba_id', $wabaId)
            ->where(function ($query) use ($contactPhoneMap) {
                foreach ($contactPhoneMap as $item) {
                    $query->orWhere(function ($q) use ($item) {
                        $q->where('contact_id', $item['contact_id'])
                            ->where('phone_number', $item['phone']);
                    });
                }
            })
            ->get();

        // Index existing conversations by contact_id + phone_number
        $existingByKey = [];
        foreach ($existingConversations as $conv) {
            $key = $conv->contact_id.'_'.$conv->phone_number;
            $existingByKey[$key] = $conv;
        }

        // Prepare new conversations for bulk insert
        $newConversations = [];
        $now = now();

        foreach ($contactPhoneMap as $item) {
            $key = $item['key'];

            // Check if conversation already exists
            if (isset($existingByKey[$key])) {
                $conversations[$key] = $existingByKey[$key];
            } else {
                // Create new conversation
                $conversationId = \Illuminate\Support\Str::ulid();
                $newConversations[] = [
                    'id' => $conversationId,
                    'tenant_id' => $this->broadcast->tenant_id,
                    'contact_id' => $item['contact_id'],
                    'phone_number' => $item['phone'],
                    'waba_id' => $wabaId,
                    'last_message_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                // Store conversation for this contact-phone combination
                $conversations[$key] = (object) [
                    'id' => $conversationId,
                ];
            }
        }

        // Bulk insert new conversations
        if (! empty($newConversations)) {
            DB::table('conversations')->insert($newConversations);
        }

        return $conversations;
    }

    /**
     * Send messages to WhatsApp API in batches
     */
    protected function sendMessagesToWhatsApp(array $messages, Collection $contacts, MetaService $metaService, TemplateComponentBuilderService $templateBuilder, WhatsAppRateLimiter $rateLimiter): void
    {
        $phoneNumberId = $this->broadcast->phoneNumber->meta_id ?? null;
        if (! $phoneNumberId) {
            return;
        }

        foreach ($messages as $message) {
            $waitTime = $rateLimiter->waitUntilAvailable($phoneNumberId, 1);
            if ($waitTime > 0) {
                usleep($waitTime * 1000);
            }

            // Reserve the slot after waiting
            $rateLimiter->attempt($phoneNumberId, 1);

            try {
                $contact = $contacts->first(function ($c) use ($message) {
                    $phoneNumbers = $this->getContactPhoneNumbers($c);

                    return in_array($message['to_phone'], $phoneNumbers);
                });

                if (! $contact) {
                    continue;
                }

                // Build template components
                $components = $templateBuilder->buildForContact(
                    $this->broadcast->template,
                    $contact,
                    $this->broadcast->variables ?? []
                );

                // Send the message
                $response = $metaService->sendTemplateMessage(
                    $phoneNumberId,
                    $message['to_phone'],
                    $this->broadcast->template->name,
                    $this->broadcast->template->language,
                    $components
                );

                if (isset($response['error'])) {
                    throw new \Exception($response['error']['message'] ?? 'Failed to send message');
                }

                Message::where('id', $message['id'])->update([
                    'status' => MessageStatus::SENT->value,
                    'sent_at' => now(),
                    'meta_id' => $response['messages'][0]['id'],
                ]);
            } catch (Throwable $e) {
                Message::where('id', $message['id'])->update([
                    'status' => MessageStatus::FAILED->value,
                    'failed_at' => now(),
                    'errors' => json_encode(['error' => $e->getMessage()]),
                ]);

                $this->broadcast->increment('failed_count');
            }
        }
    }

    /**
     * Get all contact phone numbers from field values
     */
    protected function getContactPhoneNumbers(Contact $contact): array
    {
        $phoneField = $contact->fieldValues->first(function ($fieldValue) {
            return $fieldValue->field && $fieldValue->field->internal_name === 'Phone';
        });

        $phoneNumbers = is_array($phoneField->value) ? $phoneField->value : [$phoneField->value];

        return array_filter($phoneNumbers, function ($phone) {
            return ! empty($phone);
        });
    }

    /**
     * Get first contact phone number (for backwards compatibility)
     */
    protected function getContactPhoneNumber(Contact $contact): ?string
    {
        $phoneNumbers = $this->getContactPhoneNumbers($contact);

        return $phoneNumbers[0] ?? null;
    }

    /**
     * Build message content with variables replaced
     */
    protected function buildMessageContent(Contact $contact, TemplateComponentBuilderService $templateBuilder): string
    {
        if (! $this->broadcast->template) {
            return '';
        }

        $variables = $templateBuilder->getContactVariables(
            $contact,
            $this->broadcast->variables ?? []
        );

        return $templateBuilder->replaceVariablesInContent(
            $this->broadcast->template->body ?? '',
            $variables
        );
    }

    /**
     * Mark broadcast as completed
     */
    protected function markBroadcastAsCompleted(): void
    {
        // Update counts from actual messages
        $stats = DB::table('messages')
            ->select(
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status = '".MessageStatus::SENT->value."' THEN 1 ELSE 0 END) as sent"),
                DB::raw("SUM(CASE WHEN status = '".MessageStatus::DELIVERED->value."' THEN 1 ELSE 0 END) as delivered"),
                DB::raw("SUM(CASE WHEN status = '".MessageStatus::READ->value."' THEN 1 ELSE 0 END) as read"),
                DB::raw("SUM(CASE WHEN status = '".MessageStatus::FAILED->value."' THEN 1 ELSE 0 END) as failed")
            )
            ->where('broadcast_id', $this->broadcast->id)
            ->first();

        $this->broadcast->update([
            'status' => BroadcastStatus::COMPLETED,
            'sent_at' => now(),
            'sent_count' => $stats->sent ?? 0,
            'delivered_count' => $stats->delivered ?? 0,
            'readed_count' => $stats->read ?? 0,
            'failed_count' => $stats->failed ?? 0,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('Broadcast job failed', [
            'broadcast_id' => $this->broadcast->id,
            'offset' => $this->offset,
            'error' => $exception->getMessage(),
        ]);

        $this->broadcast->update([
            'status' => BroadcastStatus::FAILED,
        ]);
    }
}
