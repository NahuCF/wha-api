<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ConversationService
{
    /**
     * Search conversations with filters
     */
    public function searchConversations(array $filters, ?User $user): LengthAwarePaginator
    {
        $rowsPerPage = data_get($filters, 'rows_per_page', 15);
        $onlyUnassigned = data_get($filters, 'only_unassigned');
        $userId = data_get($filters, 'user_id');
        $onlyPinned = data_get($filters, 'only_pinned');
        $onlySolved = data_get($filters, 'only_solved');
        $onlyOpened = data_get($filters, 'only_opened');
        $onlyMentioned = data_get($filters, 'only_mentioned');
        $search = data_get($filters, 'search');
        $searchType = data_get($filters, 'search_type', 'contact');

        $query = Conversation::query()
            ->with(['contact', 'assignedUser', 'latestMessage', 'waba', 'phoneNumber']);

        if ($search && $searchType === 'message') {
            $query->whereHas('messages', function ($q) use ($search) {
                $q->where('content', 'ILIKE', '%'.$search.'%');
            });
        } elseif ($search && $searchType === 'contact') {
            $query->whereHas('contact', function ($q) use ($search) {
                $q->whereHas('fieldValues', function ($subQuery) use ($search) {
                    $subQuery->where('value', 'ILIKE', '%'.$search.'%')
                        ->whereHas('field', function ($fieldQuery) {
                            $fieldQuery->where('internal_name', 'Name')
                                ->where('is_primary_field', true);
                        });
                });
            });
        }

        $query->when($onlyUnassigned, fn ($q) => $q->whereNull('user_id')->where('is_solved', false))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when($onlyPinned && $user, fn ($q) => $q->pinnedBy($user))
            ->when($onlySolved, fn ($q) => $q->where('is_solved', true))
            ->when($onlyOpened, fn ($q) => $q->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            }))
            ->when($onlyMentioned && $user, fn ($q) => $q->whereHas('messages', function ($query) use ($user) {
                $query->whereRaw('EXISTS (
                    SELECT 1 FROM jsonb_array_elements(mentions::jsonb) AS elem
                    WHERE elem->>? = ?
                )', [$user->name, $user->id]);
            }))
            ->when($user, fn ($q) => $q->with(['pinnedByUsers' => function ($query) use ($user) {
                $query->where('user_id', $user->id);
            }]));

        $conversations = $query->orderBy('last_message_at', 'asc')->paginate($rowsPerPage);

        if ($search && $searchType === 'message') {
            $messagesPerPage = data_get($filters, 'messages_per_page', 15);
            $this->attachMatchingMessageData($conversations, $search, $messagesPerPage);
        }

        return $conversations;
    }

    /**
     * Attach matching message data to conversations
     */
    public function attachMatchingMessageData(LengthAwarePaginator $conversations, string $search, int $messagesPerPage): void
    {
        $conversationIds = $conversations->pluck('id')->toArray();

        if (empty($conversationIds)) {
            return;
        }

        $matchingMessages = $this->findMatchingMessages($conversationIds, $search, $messagesPerPage);

        // Attach matching message data to each conversation
        foreach ($conversations as $conversation) {
            if ($matchingMessages->has($conversation->id)) {
                $conversation->setAttribute('matching_message_data', $matchingMessages->get($conversation->id));
            }
        }
    }

    /**
     * Find matching messages for given conversations
     */
    private function findMatchingMessages(array $conversationIds, string $search, int $messagesPerPage)
    {
        $tenantId = tenant('id');
        $searchPattern = '%'.$search.'%';

        $matchingMessages = DB::select("
            WITH latest_matches AS (
                SELECT 
                    conversation_id,
                    id,
                    content,
                    created_at,
                    ROW_NUMBER() OVER (PARTITION BY conversation_id ORDER BY created_at DESC) as rn
                FROM messages
                WHERE conversation_id IN ('".implode("','", $conversationIds)."')
                  AND content ILIKE ?
                  AND tenant_id = ?
            ),
            message_positions AS (
                SELECT 
                    lm.conversation_id,
                    lm.id as message_id,
                    lm.content as message_content,
                    lm.created_at,
                    COUNT(m.id) as newer_messages_count
                FROM latest_matches lm
                LEFT JOIN messages m ON 
                    m.conversation_id = lm.conversation_id 
                    AND m.created_at > lm.created_at
                    AND m.tenant_id = ?
                WHERE lm.rn = 1
                GROUP BY lm.conversation_id, lm.id, lm.content, lm.created_at
            )
            SELECT * FROM message_positions
        ", [$searchPattern, $tenantId, $tenantId]);

        // Convert to keyed collection
        return collect($matchingMessages)->mapWithKeys(function ($item) use ($messagesPerPage) {
            $positionFromEnd = $item->newer_messages_count + 1;
            $pageNumber = ceil($positionFromEnd / $messagesPerPage);

            return [
                $item->conversation_id => [
                    'message_content' => $item->message_content,
                    'message_id' => $item->message_id,
                    'page' => $pageNumber,
                    'position_from_end' => $positionFromEnd,
                ],
            ];
        });
    }

    /**
     * Get conversation statistics for a user
     */
    public function getConversationStats(User $user, ?string $updateView = null): array
    {
        if ($updateView) {
            $columnName = 'last_'.$updateView.'_view_at';
            $user->update([$columnName => now()]);
        }

        $baseQuery = Conversation::query();
        $defaultTime = now()->subYears(10);

        $lastUnassignedView = $user->last_unassigned_view_at ?? $defaultTime;
        $lastMineView = $user->last_mine_view_at ?? $defaultTime;
        $lastOpenedView = $user->last_opened_view_at ?? $defaultTime;
        $lastResolvedView = $user->last_resolved_view_at ?? $defaultTime;
        $lastMentionedView = $user->last_mentioned_view_at ?? $defaultTime;

        return [
            'unassigned' => $baseQuery->clone()
                ->whereNull('user_id')
                ->where('is_solved', false)
                ->where(function ($q) use ($lastUnassignedView) {
                    $q->where('created_at', '>', $lastUnassignedView)
                        ->orWhere('last_message_at', '>', $lastUnassignedView);
                })
                ->count(),
            'mine' => $baseQuery->clone()
                ->where('user_id', $user->id)
                ->where(function ($q) use ($lastMineView) {
                    $q->where('last_message_at', '>', $lastMineView)
                        ->orWhere(function ($q2) use ($lastMineView) {
                            $q2->whereNull('last_message_at')
                                ->where('created_at', '>', $lastMineView);
                        });
                })
                ->count(),
            'opened' => $baseQuery->clone()
                ->where('expires_at', '>', now())
                ->where('is_solved', false)
                ->where(function ($q) use ($lastOpenedView) {
                    $q->where('created_at', '>', $lastOpenedView)
                        ->orWhere('last_message_at', '>', $lastOpenedView);
                })
                ->count(),
            'resolved' => $baseQuery->clone()
                ->where('is_solved', true)
                ->where('updated_at', '>', $lastResolvedView)
                ->count(),
            'mentioned' => $baseQuery->clone()
                ->whereHas('messages', function ($query) use ($user, $lastMentionedView) {
                    $query->whereRaw('EXISTS (
                        SELECT 1 FROM jsonb_array_elements(mentions::jsonb) AS elem
                        WHERE elem->>? = ?
                    )', [$user->name, $user->id])
                        ->where('created_at', '>', $lastMentionedView);
                })
                ->count(),
        ];
    }
}
