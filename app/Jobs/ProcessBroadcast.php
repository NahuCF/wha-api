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
use Throwable;

class ProcessBroadcast implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Broadcast $broadcast;

    protected int $batchSize;

    protected int $offset;

    public $tries = 5;

    public $maxExceptions = 3;

    public $timeout = 600;

    /**
     * Create a new job instance.
     */
    public function __construct(Broadcast $broadcast, int $batchSize = 500, int $offset = 0)
    {
        $this->broadcast = $broadcast;
        $this->batchSize = $batchSize;
        $this->offset = $offset;

        $this->onQueue('broadcasts');
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

            // Add contacts to active broadcast tracking
            $this->addContactsToActiveBroadcasts($allContactIds);

            // When send_to_all_numbers is true, we need to track contact-phone combinations
            // Otherwise, just track contacts
            if ($this->broadcast->send_to_all_numbers) {
                $remainingWork = $this->getRemainingContactPhoneCombinations($allContactIds);
                if ($remainingWork->isEmpty()) {
                    $this->markBroadcastAsCompleted();

                    return;
                }

                $remainingContactIds = $remainingWork->pluck('contact_id')->unique();
            } else {
                $processedContactIds = $this->getProcessedContactIds();
                $remainingContactIds = $allContactIds->diff($processedContactIds);

                if ($remainingContactIds->isEmpty()) {
                    $this->markBroadcastAsCompleted();

                    return;
                }
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

                    // Build the rendered template content for searching
                    $renderedContent = $this->buildMessageContent($contact, $templateBuilder);

                    $messagesToInsert[] = [
                        'id' => \Illuminate\Support\Str::ulid(),
                        'tenant_id' => $this->broadcast->tenant_id,
                        'conversation_id' => $conversation->id,
                        'contact_id' => $contact->id,
                        'broadcast_id' => $this->broadcast->id,
                        'template_id' => $this->broadcast->template_id,
                        'direction' => MessageDirection::OUTBOUND->value,
                        'type' => MessageType::TEMPLATE->value,
                        'status' => MessageStatus::PENDING->value,
                        'source' => MessageSource::BROADCAST->value,
                        'content' => $renderedContent,
                        'rendered_content' => $renderedContent, // Store rendered content for search
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

    /**
     * Get remaining contact-phone combinations that haven't been processed
     */
    protected function getRemainingContactPhoneCombinations(Collection $allContactIds): Collection
    {
        $sentCombinations = collect();

        // Set conbinations contact_id - phone
        DB::table('messages')
            ->select('conversations.contact_id', 'messages.to_phone')
            ->join('conversations', 'messages.conversation_id', '=', 'conversations.id')
            ->where('messages.broadcast_id', $this->broadcast->id)
            ->whereNotNull('conversations.contact_id')
            ->orderBy('messages.id')
            ->chunk(1000, function ($messages) use (&$sentCombinations) {
                $messages->each(function ($msg) use (&$sentCombinations) {
                    $sentCombinations->push($msg->contact_id.'_'.$msg->to_phone);
                });
            });

        $sentCombinations = $sentCombinations->unique();

        // Get all contact-phone combinations that should be processed
        $remainingWork = collect();

        // Process contacts in chunks to get their phone numbers
        Contact::query()
            ->whereIn('id', $allContactIds)
            ->with(['fieldValues' => function ($query) {
                $query->with('field');
            }])
            ->orderBy('id')
            ->chunk(100, function ($contacts) use (&$remainingWork, $sentCombinations) {
                foreach ($contacts as $contact) {
                    $phoneNumbers = $this->getContactPhoneNumbers($contact);

                    foreach ($phoneNumbers as $phone) {
                        $key = $contact->id.'_'.$phone;
                        // Only add if not already sent
                        if (! $sentCombinations->contains($key)) {
                            $remainingWork->push([
                                'contact_id' => $contact->id,
                                'phone' => $phone,
                            ]);
                        }
                    }
                }
            });

        return $remainingWork;
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
                            ->where('to_phone', $item['phone']);
                    });
                }
            })
            ->get();

        // Index existing conversations by contact_id + phone_number
        $existingByKey = [];
        foreach ($existingConversations as $conv) {
            $key = $conv->contact_id.'_'.$conv->to_phone;
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
                    'phone_number_id' => $this->broadcast->phone_number_id,
                    'contact_id' => $item['contact_id'],
                    'to_phone' => $item['phone'],
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

        // Calculate actual unique recipients based on unique phone numbers
        $actualRecipients = DB::table('messages')
            ->where('broadcast_id', $this->broadcast->id)
            ->distinct('to_phone')
            ->count('to_phone');

        $this->broadcast->update([
            'status' => BroadcastStatus::COMPLETED,
            'sent_at' => now(),
            'recipients_count' => $actualRecipients,
            'sent_count' => $stats->sent ?? 0,
            'delivered_count' => $stats->delivered ?? 0,
            'readed_count' => $stats->read ?? 0,
            'failed_count' => $stats->failed ?? 0,
        ]);

        // Remove contacts from active broadcast tracking
        $allContactIds = $this->getContactIds();
        $this->removeContactsFromActiveBroadcasts($allContactIds);
    }

    /**
     * Add contacts to active broadcast tracking
     */
    protected function addContactsToActiveBroadcasts(Collection $contactIds): void
    {
        if ($contactIds->isEmpty()) {
            return;
        }

        $broadcastId = $this->broadcast->id;
        $tenantId = $this->broadcast->tenant_id;

        // Process in chunks for performance
        $contactIds->chunk(1000)->each(function ($chunk) use ($broadcastId, $tenantId) {
            foreach ($chunk as $contactId) {
                DB::statement('
                    INSERT INTO active_broadcast_contacts (contact_id, tenant_id, broadcast_count, broadcast_ids, updated_at)
                    VALUES (?, ?, 1, ?::jsonb, NOW())
                    ON CONFLICT (contact_id) DO UPDATE SET
                        broadcast_count = active_broadcast_contacts.broadcast_count + 1,
                        broadcast_ids = CASE 
                            WHEN active_broadcast_contacts.broadcast_ids IS NULL THEN ?::jsonb
                            ELSE active_broadcast_contacts.broadcast_ids || ?::jsonb
                        END,
                        updated_at = NOW()
                ', [
                    $contactId,
                    $tenantId,
                    json_encode([$broadcastId]),
                    json_encode([$broadcastId]),
                    json_encode([$broadcastId]),
                ]);
            }
        });
    }

    /**
     * Remove contacts from active broadcast tracking
     */
    protected function removeContactsFromActiveBroadcasts(Collection $contactIds): void
    {
        if ($contactIds->isEmpty()) {
            return;
        }

        $broadcastId = $this->broadcast->id;

        // Process in chunks for performance
        $contactIds->chunk(1000)->each(function ($chunk) use ($broadcastId) {
            foreach ($chunk as $contactId) {
                // Get current data
                $current = DB::table('active_broadcast_contacts')
                    ->where('contact_id', $contactId)
                    ->first();

                if (! $current) {
                    continue;
                }

                $broadcastIds = json_decode($current->broadcast_ids, true) ?? [];
                $broadcastIds = array_filter($broadcastIds, fn ($id) => $id !== $broadcastId);
                $newCount = count($broadcastIds);

                if ($newCount === 0) {
                    // No more active broadcasts, delete the record
                    DB::table('active_broadcast_contacts')
                        ->where('contact_id', $contactId)
                        ->delete();
                } else {
                    // Update with remaining broadcasts
                    DB::table('active_broadcast_contacts')
                        ->where('contact_id', $contactId)
                        ->update([
                            'broadcast_count' => $newCount,
                            'broadcast_ids' => json_encode(array_values($broadcastIds)),
                            'updated_at' => now(),
                        ]);
                }
            }
        });
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        $this->broadcast->update([
            'status' => BroadcastStatus::FAILED,
        ]);

        tenancy()->initialize($this->broadcast->tenant);
        $allContactIds = $this->getContactIds();
        $this->removeContactsFromActiveBroadcasts($allContactIds);
    }
}
