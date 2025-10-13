<?php

namespace App\Services;

use App\Enums\BotAction;
use App\Enums\BotNodeType;
use App\Enums\BotSessionStatus;
use App\Enums\BotTriggerType;
use App\Enums\MessageDirection;
use App\Enums\MessageSource;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Jobs\SendWhatsAppMessage;
use App\Models\Bot;
use App\Models\BotNode;
use App\Models\BotSession;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\DB;

class BotService
{
    public function findBotForMessage(string $message, string $tenantId): ?Bot
    {
        // Find bots that have an active flow
        $bots = Bot::where('tenant_id', $tenantId)
            ->whereHas('activeFlow')
            ->where('trigger_type', BotTriggerType::KEYWORD)
            ->get();

        foreach ($bots as $bot) {
            if ($bot->matchesKeyword($message)) {
                return $bot;
            }
        }

        // Then check for any-message bots with active flows
        return Bot::where('tenant_id', $tenantId)
            ->whereHas('activeFlow')
            ->where('trigger_type', BotTriggerType::ANY_MESSAGE)
            ->first();
    }

    public function handleIncomingMessage(Message $message, Conversation $conversation, Contact $contact): void
    {
        $tenantId = $conversation->tenant_id;

        // Check if there's an active bot session
        $session = BotSession::where('conversation_id', $conversation->id)
            ->whereIn('status', [BotSessionStatus::ACTIVE, BotSessionStatus::WAITING])
            ->first();

        if ($session) {
            $this->continueSession($session, $message);

            return;
        }

        $bot = $this->findBotForMessage($message->content ?? '', $tenantId);

        if ($bot) {
            $this->startBotSession($bot, $conversation, $contact);
        }
    }

    public function startBotSession(Bot $bot, Conversation $conversation, Contact $contact): ?BotSession
    {
        return DB::transaction(function () use ($bot, $conversation, $contact) {
            $conversation = Conversation::where('id', $conversation->id)->lockForUpdate()->first();

            $existingSession = BotSession::where('conversation_id', $conversation->id)
                ->whereIn('status', [BotSessionStatus::ACTIVE, BotSessionStatus::WAITING])
                ->first();

            if ($existingSession) {
                $existingSession->bot->increment('completed_sessions');
                if ($existingSession->flow) {
                    $existingSession->flow->increment('completed_sessions');
                }
                $existingSession->update(['status' => BotSessionStatus::COMPLETED]);
            }

            $activeFlow = $bot->activeFlow;

            if (! $activeFlow) {
                return null;
            }

            $startNode = $activeFlow->getStartNode();

            if (! $startNode) {
                return null;
            }

            $session = BotSession::create([
                'tenant_id' => $conversation->tenant_id,
                'bot_id' => $bot->id,
                'bot_flow_id' => $activeFlow->id,
                'conversation_id' => $conversation->id,
                'contact_id' => $contact->id,
                'current_node_id' => $startNode->node_id,
                'status' => BotSessionStatus::ACTIVE,
                'variables' => [],
                'history' => [],
                'last_interaction_at' => now(),
                'timeout_at' => now()->addMinutes($bot->wait_time_minutes),
            ]);

            $bot->increment('total_sessions');
            $activeFlow->increment('total_sessions');

            $this->executeNode($session, $startNode);

            return $session;
        });
    }

    public function continueSession(BotSession $session, Message $message): void
    {
        // Check if session has timed out
        if ($session->isExpired()) {
            $this->handleTimeout($session);

            return;
        }

        // Update session
        $session->update([
            'status' => BotSessionStatus::ACTIVE,
            'last_interaction_at' => now(),
        ]);

        $currentNode = $session->currentNode();

        if (! $currentNode) {
            return;
        }

        $nodeType = $currentNode->type;
        $variableName = $currentNode->variable_name;
        $useFallback = $currentNode->use_fallback;

        // Handle user response based on current node type
        if ($nodeType === BotNodeType::QUESTION_BUTTON) {
            // Store response in variable if configured
            if ($variableName) {
                $session->setVariable($variableName, $message->content);
            }

            // Find next node based on response
            $nextNode = $currentNode->getNextNode($message->content, $session->variables ?? []);

            if (! $nextNode && ! $useFallback) {
                $bot = $session->bot;

                if ($bot->no_match_message) {
                    $this->sendMessage($session->conversation, $bot->no_match_message);
                }

                // Execute no match action if configured and not MESSAGE
                if ($bot->no_match_action && $bot->no_match_action !== BotAction::MESSAGE) {
                    $this->executeAction(
                        $session->conversation,
                        $bot->no_match_action,
                        $bot->no_match_assign_user_id,
                        $bot->no_match_assign_bot_id
                    );

                    // If action is not NO_ACTION, end the session
                    if ($bot->no_match_action !== BotAction::NO_ACTION) {
                        $session->markAsCompleted();

                        return;
                    }
                }

                // Stay on current node if NO_ACTION, MESSAGE or no action configured
                $session->markAsWaiting();

                return;
            }

            if ($nextNode) {
                $this->executeNode($session, $nextNode);
            }
        } else {
            // For non-question nodes, just move to next node
            $nextNode = $currentNode->getNextNode(null, $session->variables ?? []);

            if ($nextNode) {
                $this->executeNode($session, $nextNode);
            } else {
                // No more nodes, end session
                $session->markAsCompleted();
                $this->handleEndConversation($session);
            }
        }
    }

    public function executeNode(BotSession $session, BotNode $node): void
    {
        // Check if session has expired before executing
        if ($session->isExpired()) {
            $this->handleTimeout($session);

            return;
        }

        // Add to history
        $session->addToHistory($node->node_id);

        // Update current node
        $session->update(['current_node_id' => $node->node_id]);

        switch ($node->type) {
            case BotNodeType::MESSAGE:
                $this->executeMessageNode($session, $node);
                break;

            case BotNodeType::TEMPLATE:
                $this->executeTemplateNode($session, $node);
                break;

            case BotNodeType::IMAGE:
            case BotNodeType::VIDEO:
            case BotNodeType::AUDIO:
            case BotNodeType::DOCUMENT:
                $this->executeMediaNode($session, $node);
                break;

            case BotNodeType::QUESTION_BUTTON:
                $this->executeQuestionButtonNode($session, $node);
                break;

            case BotNodeType::CONDITION:
                $this->executeConditionNode($session, $node);
                break;

            case BotNodeType::LOCATION:
                $this->executeLocationNode($session, $node);
                break;

            case BotNodeType::START_AGAIN:
                $this->executeStartAgainNode($session, $node);

                return; // Important: return to avoid continuing the flow

            case BotNodeType::MARK_AS_SOLVED:
                $this->executeMarkAsSolvedNode($session, $node);
                break;

            case BotNodeType::ASSIGN_CHAT:
                $this->executeAssignNode($session, $node);
                break;

            case BotNodeType::WORKING_HOURS:
                $this->executeWorkingHoursNode($session, $node);
                break;

            case BotNodeType::SET_VARIABLE:
                $this->executeSetVariableNode($session, $node);
                break;
        }

        // If not a question or terminal node, check for next node
        if (! in_array($node->type, [BotNodeType::QUESTION_BUTTON, BotNodeType::MARK_AS_SOLVED, BotNodeType::ASSIGN_CHAT, BotNodeType::START_AGAIN])) {
            $nextNode = $node->getNextNode(null, $session->variables ?? []);

            if ($nextNode) {
                $this->executeNode($session, $nextNode);
            } else {
                $session->markAsCompleted();
                $this->handleEndConversation($session);
            }
        } elseif ($node->type === BotNodeType::QUESTION_BUTTON) {
            $session->markAsWaiting();
        }
    }

    private function executeMessageNode(BotSession $session, $node): void
    {
        $content = is_array($node) ? ($node['data']['content'] ?? '') : $node->content;
        $content = $this->replaceVariables($content, $session);
        $this->sendMessage($session->conversation, $content);
    }

    private function executeTemplateNode(BotSession $session, $node): void
    {
        $templateId = is_array($node) ? ($node['data']['template_id'] ?? null) : $node->template_id;

        if (! $templateId) {
            $this->executeMessageNode($session, $node);

            return;
        }

        $conversation = $session->conversation;
        $phoneNumber = $conversation->phoneNumber;
        $waba = $conversation->waba;

        // Get template and prepare parameters with variable replacement
        $parameters = is_array($node) ? ($node['data']['template_parameters'] ?? []) : ($node->template_parameters ?? []);
        foreach ($parameters as &$param) {
            if (is_string($param)) {
                $param = $this->replaceVariables($param, $session);
            }
        }

        $message = Message::create([
            'tenant_id' => $conversation->tenant_id,
            'conversation_id' => $conversation->id,
            'contact_id' => $session->contact_id,
            'direction' => MessageDirection::OUTBOUND,
            'type' => MessageType::TEMPLATE,
            'status' => MessageStatus::PENDING,
            'source' => MessageSource::BOT,
            'template_id' => $templateId,
            'template_parameters' => $parameters,
            'to_phone' => $conversation->contact_phone,
        ]);

        SendWhatsAppMessage::dispatch(
            messageData: $message->toArray(),
            tenantId: $conversation->tenant_id,
            phoneNumberId: $phoneNumber->meta_id,
            wabaId: $waba->id,
            conversationId: $conversation->id,
        );
    }

    private function executeMediaNode(BotSession $session, $node): void
    {
        $conversation = $session->conversation;
        $phoneNumber = $conversation->phoneNumber;
        $waba = $conversation->waba;

        $nodeType = is_array($node) ? BotNodeType::tryFrom($node['type'] ?? '') : $node->type;
        $messageType = match ($nodeType) {
            BotNodeType::IMAGE => MessageType::IMAGE,
            BotNodeType::VIDEO => MessageType::VIDEO,
            BotNodeType::AUDIO => MessageType::AUDIO,
            BotNodeType::DOCUMENT => MessageType::DOCUMENT,
            default => MessageType::TEXT,
        };

        $message = Message::create([
            'tenant_id' => $conversation->tenant_id,
            'conversation_id' => $conversation->id,
            'contact_id' => $session->contact_id,
            'direction' => MessageDirection::OUTBOUND,
            'type' => $messageType,
            'status' => MessageStatus::PENDING,
            'source' => MessageSource::BOT,
            'content' => (is_array($node) ? ($node['data']['content'] ?? null) : $node->content) ? $this->replaceVariables(is_array($node) ? ($node['data']['content'] ?? '') : $node->content, $session) : null,
            'media' => [
                'url' => is_array($node) ? ($node['data']['media_url'] ?? null) : $node->media_url,
                'type' => is_array($node) ? ($node['data']['media_type'] ?? null) : $node->media_type,
            ],
            'to_phone' => $conversation->contact_phone,
        ]);

        SendWhatsAppMessage::dispatch(
            messageData: $message->toArray(),
            tenantId: $conversation->tenant_id,
            phoneNumberId: $phoneNumber->meta_id,
            wabaId: $waba->id,
            conversationId: $conversation->id,
        );
    }

    private function executeQuestionButtonNode(BotSession $session, $node): void
    {
        $conversation = $session->conversation;
        $phoneNumber = $conversation->phoneNumber;
        $waba = $conversation->waba;

        // Prepare interactive button message
        $nodeContent = is_array($node) ? ($node['data']['content'] ?? '') : $node->content;
        $content = $this->replaceVariables($nodeContent, $session);

        // WhatsApp allows maximum 3 buttons
        $buttons = [];
        $options = is_array($node) ? ($node['data']['options'] ?? []) : ($node->options ?? []);
        if ($options && count($options) > 0) {
            foreach (array_slice($options, 0, 3) as $option) {
                $buttons[] = [
                    'type' => 'reply',
                    'reply' => [
                        'id' => $option['id'] ?? uniqid('btn_'),
                        'title' => substr($option['title'] ?? $option['label'] ?? '', 0, 20), // Max 20 chars
                    ],
                ];
            }
        }

        // Create interactive message
        $message = Message::create([
            'tenant_id' => $conversation->tenant_id,
            'conversation_id' => $conversation->id,
            'contact_id' => $session->contact_id,
            'direction' => MessageDirection::OUTBOUND,
            'type' => MessageType::INTERACTIVE,
            'status' => MessageStatus::PENDING,
            'source' => MessageSource::BOT,
            'content' => $content,
            'interactive_data' => [
                'type' => 'button',
                'body' => [
                    'text' => $content,
                ],
                'action' => [
                    'buttons' => $buttons,
                ],
            ],
            'to_phone' => $conversation->contact_phone,
        ]);

        SendWhatsAppMessage::dispatch(
            messageData: $message->toArray(),
            tenantId: $conversation->tenant_id,
            phoneNumberId: $phoneNumber->meta_id,
            wabaId: $waba->id,
            conversationId: $conversation->id,
        );
    }

    private function executeAssignNode(BotSession $session, $node): void
    {
        $conversation = $session->conversation;

        $assignType = is_array($node) ? ($node['data']['assign_type'] ?? null) : $node->assign_type;
        $assignToUserId = is_array($node) ? ($node['data']['assign_to_user_id'] ?? null) : $node->assign_to_user_id;
        $assignToBotId = is_array($node) ? ($node['data']['assign_to_bot_id'] ?? null) : $node->assign_to_bot_id;

        if ($assignType === 'user' && $assignToUserId) {
            $conversation->update(['assigned_user_id' => $assignToUserId]);
            $session->markAsCompleted();
        } elseif ($assignType === 'bot' && $assignToBotId) {
            $newBot = Bot::find($assignToBotId);
            if ($newBot && $newBot->activeFlow) {
                $this->startBotSession($newBot, $conversation, $session->contact);
            }
        } else {
            $conversation->update(['assigned_user_id' => null]);
            $session->markAsCompleted();
        }
    }

    private function executeMarkAsSolvedNode(BotSession $session, $node): void
    {
        // Mark the conversation as solved
        $conversation = $session->conversation;
        $conversation->update([
            'is_solved' => true,
            'solved_at' => now(),
        ]);

        // End the bot session
        $session->markAsCompleted();
    }

    private function executeConditionNode(BotSession $session, $node): void
    {
        $conditionMet = false;
        if (is_array($node)) {
            $conditions = $node['data']['conditions'] ?? [];
            $conditionMet = $this->evaluateConditions($conditions, $session->variables ?? [], $session->contact);
        } else {
            $conditionMet = $node->evaluateCondition($session->variables ?? [], $session->contact);
        }

        $conditionPath = $conditionMet ? 'true' : 'false';

        $nextNode = null;
        if (is_array($node) && $session->botVersion) {
            // Find flow in version
            $nodeId = $node['node_id'] ?? $node['id'] ?? null;
            $flows = $session->botVersion->getFlowsFromNode($nodeId);
            foreach ($flows as $flow) {
                if (($flow['condition_value'] ?? null) === $conditionPath) {
                    $targetNodeId = $flow['target_node_id'] ?? null;
                    $nextNode = $targetNodeId ? $session->botVersion->getNodeById($targetNodeId) : null;
                    break;
                }
            }
        } else {
            $edge = $session->flow->edges()
                ->where('source_node_id', $node->node_id)
                ->where('condition_value', $conditionPath)
                ->first();

            if ($edge) {
                $nextNode = $session->flow->nodes()->where('node_id', $edge->target_node_id)->first();
            }
        }

        if ($nextNode) {
            $this->executeNode($session, $nextNode);
        } else {
            $session->markAsCompleted();
        }
    }

    private function executeStartAgainNode(BotSession $session, $node): void
    {
        // Simply jump back to the start node
        $startNode = null;
        if ($session->flow) {
            $startNode = $session->flow->getStartNode();
        }

        if ($startNode) {
            // Just execute the start node again - keep all variables and history
            $this->executeNode($session, $startNode);
        }
    }

    private function executeLocationNode(BotSession $session, $node): void
    {
        $latitude = is_array($node) ? ($node['data']['latitude'] ?? null) : $node->latitude;
        $longitude = is_array($node) ? ($node['data']['longitude'] ?? null) : $node->longitude;

        if (! $latitude || ! $longitude) {
            return;
        }

        $conversation = $session->conversation;
        $phoneNumber = $conversation->phoneNumber;
        $waba = $conversation->waba;

        $message = Message::create([
            'tenant_id' => $conversation->tenant_id,
            'conversation_id' => $conversation->id,
            'contact_id' => $session->contact_id,
            'direction' => MessageDirection::OUTBOUND,
            'type' => MessageType::LOCATION,
            'status' => MessageStatus::PENDING,
            'source' => MessageSource::BOT,
            'content' => is_array($node) ? ($node['data']['location_name'] ?? null) : ($node->location_name ?? null),
            'location_data' => [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'name' => is_array($node) ? ($node['data']['location_name'] ?? null) : $node->location_name,
                'address' => is_array($node) ? ($node['data']['location_address'] ?? null) : $node->location_address,
            ],
            'to_phone' => $conversation->contact_phone,
        ]);

        SendWhatsAppMessage::dispatch(
            messageData: $message->toArray(),
            tenantId: $conversation->tenant_id,
            phoneNumberId: $phoneNumber->meta_id,
            wabaId: $waba->id,
            conversationId: $conversation->id,
        );
    }

    private function executeWorkingHoursNode(BotSession $session, $node): void
    {
        $tenantSettings = \App\Models\TenantSettings::where('tenant_id', $session->tenant_id)->first();

        $isAvailable = true;
        if ($tenantSettings) {
            $isAvailable = $tenantSettings->isWithinWorkingHours();
        }

        $conditionPath = $isAvailable ? 'Available' : 'Unavailable';

        $nextNode = null;
        if (is_array($node) && $session->botVersion) {
            // Find flow in version
            $nodeId = $node['node_id'] ?? $node['id'] ?? null;
            $flows = $session->botVersion->getFlowsFromNode($nodeId);
            foreach ($flows as $flow) {
                if (($flow['condition_value'] ?? null) === $conditionPath) {
                    $targetNodeId = $flow['target_node_id'] ?? null;
                    $nextNode = $targetNodeId ? $session->botVersion->getNodeById($targetNodeId) : null;
                    break;
                }
            }
        } else {
            // Find the edge from current node with the condition path
            $edge = $session->flow->edges()
                ->where('source_node_id', $node->node_id)
                ->where('condition_value', $conditionPath)
                ->first();

            if ($edge) {
                $nextNode = $session->flow->nodes()->where('node_id', $edge->target_node_id)->first();
            }
        }

        if ($nextNode) {
            $this->executeNode($session, $nextNode);
        } else {
            $session->markAsCompleted();
        }
    }

    private function executeSetVariableNode(BotSession $session, $node): void
    {
        $variables = is_array($node) ? ($node['data']['variables'] ?? null) : ($node->data['variables'] ?? null);

        if (! $variables || ! is_array($variables)) {
            return;
        }

        $interpolator = new BotVariableInterpolator($session->contact, $session->variables ?? []);

        foreach ($variables as $variable) {
            $name = $variable['variable_name'] ?? null;
            $value = $variable['value'] ?? '';

            if (! $name) {
                continue;
            }

            $processedValue = $interpolator->interpolate($value);

            $session->setVariable($name, $processedValue);

            $interpolator->addSessionVariable($name, $processedValue);
        }
    }

    private function handleTimeout(BotSession $session): void
    {
        $session->markAsTimeout();
        $bot = $session->bot;
        $conversation = $session->conversation;

        // Send timeout message if configured
        if ($bot->timeout_message) {
            $this->sendMessage($conversation, $bot->timeout_message);
        }

        // Execute timeout action
        $this->executeAction(
            $conversation,
            $bot->timeout_action,
            $bot->timeout_assign_user_id,
            $bot->timeout_assign_bot_id
        );
    }

    private function handleEndConversation(BotSession $session): void
    {
        $bot = $session->bot;
        $conversation = $session->conversation;

        // Send end message if configured
        if ($bot->end_conversation_message) {
            $this->sendMessage($conversation, $bot->end_conversation_message);
        }

        // Execute end conversation action
        if ($bot->end_conversation_action) {
            $this->executeAction(
                $conversation,
                $bot->end_conversation_action,
                $bot->end_conversation_assign_user_id,
                $bot->end_conversation_assign_bot_id
            );
        }
    }

    private function executeAction(Conversation $conversation, ?BotAction $action, ?string $userId, ?string $botId): void
    {
        if (! $action) {
            return;
        }

        switch ($action) {
            case BotAction::NO_ACTION:
                break;

            case BotAction::UNASSIGN:
                $conversation->update(['assigned_user_id' => null]);
                break;

            case BotAction::ASSIGN_USER:
                if ($userId) {
                    $conversation->update(['assigned_user_id' => $userId]);
                }
                break;

            case BotAction::ASSIGN_BOT:
                if ($botId) {
                    $bot = Bot::find($botId);
                    if ($bot) {
                        $contact = $conversation->contact;
                        $this->startBotSession($bot, $conversation, $contact);
                    }
                }
                break;

            case BotAction::MESSAGE:
                break;
        }
    }

    private function sendMessage(Conversation $conversation, string $content): void
    {
        $phoneNumber = $conversation->phoneNumber;
        $waba = $conversation->waba;

        $message = Message::create([
            'tenant_id' => $conversation->tenant_id,
            'conversation_id' => $conversation->id,
            'contact_id' => $conversation->contact_id,
            'direction' => MessageDirection::OUTBOUND,
            'type' => MessageType::TEXT,
            'status' => MessageStatus::PENDING,
            'source' => MessageSource::BOT,
            'content' => $content,
            'to_phone' => $conversation->contact_phone,
        ]);

        SendWhatsAppMessage::dispatch(
            messageData: $message->toArray(),
            tenantId: $conversation->tenant_id,
            phoneNumberId: $phoneNumber->meta_id,
            wabaId: $waba->id,
            conversationId: $conversation->id,
        );
    }

    private function replaceVariables(string $content, BotSession $session): string
    {
        // First, replace contact fields (contact.fieldName)
        if (preg_match_all('/\{\{contact\.([a-zA-Z_][a-zA-Z0-9_]*)\}\}/', $content, $matches)) {
            $contact = $session->contact;

            foreach ($matches[1] as $index => $fieldName) {
                $value = '';

                // Get contact field value from contact_field_values table
                if ($contact && $contact->fieldValues) {
                    $fieldValue = $contact->fieldValues()
                        ->whereHas('field', function ($query) use ($fieldName) {
                            $query->where('internal_name', $fieldName);
                        })
                        ->first();

                    if ($fieldValue && $fieldValue->value) {
                        $value = is_string($fieldValue->value) ? $fieldValue->value : json_encode($fieldValue->value);
                    }
                }

                $content = str_replace($matches[0][$index], $value, $content);
            }
        }

        // Then replace bot session variables
        $variables = $session->variables ?? [];
        foreach ($variables as $key => $value) {
            $content = str_replace("{{{$key}}}", $value, $content);
        }

        return $content;
    }

    private function evaluateConditions(array $conditions, array $variables, ?Contact $contact): bool
    {
        if (empty($conditions)) {
            return true;
        }

        foreach ($conditions as $condition) {
            $variableId = $condition['variable_id'] ?? null;
            $operatorValue = $condition['operator'] ?? null;
            $value = $condition['value'] ?? null;

            if (! $variableId || ! $operatorValue) {
                continue;
            }

            $botVariable = \App\Models\BotVariable::find($variableId);
            if (! $botVariable) {
                continue;
            }

            $fieldValue = $variables[$botVariable->name] ?? null;

            $operator = \App\Enums\ComparisonOperator::tryFrom($operatorValue);

            if (! $operator) {
                continue;
            }

            $result = $operator->evaluate($fieldValue, $value);

            if (! $result) {
                return false;
            }
        }

        return true;
    }
}
