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
use Illuminate\Support\Facades\Log;

class BotService
{
    public function findBotForMessage(string $message, string $tenantId): ?Bot
    {
        $bots = Bot::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('trigger_type', BotTriggerType::KEYWORD)
            ->get();

        foreach ($bots as $bot) {
            if ($bot->matchesKeyword($message)) {
                return $bot;
            }
        }

        // Then check for any-message bots
        return Bot::where('tenant_id', $tenantId)
            ->where('is_active', true)
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

        // Find matching bot
        $bot = $this->findBotForMessage($message->content ?? '', $tenantId);

        if ($bot) {
            $this->startBotSession($bot, $conversation, $contact);
        }
        // If no bot matched, do nothing (no global settings)
    }

    public function startBotSession(Bot $bot, Conversation $conversation, Contact $contact): ?BotSession
    {
        // End any existing sessions
        BotSession::where('conversation_id', $conversation->id)
            ->whereIn('status', [BotSessionStatus::ACTIVE, BotSessionStatus::WAITING])
            ->update(['status' => BotSessionStatus::COMPLETED]);

        // Find the first node to execute
        $startNode = $bot->getStartNode();

        if (! $startNode) {
            // No nodes defined for this bot
            Log::warning('Bot has no nodes defined', ['bot_id' => $bot->id]);

            return null;
        }

        // Create new session with the first node as current
        $session = BotSession::create([
            'tenant_id' => $conversation->tenant_id,
            'bot_id' => $bot->id,
            'conversation_id' => $conversation->id,
            'contact_id' => $contact->id,
            'current_node_id' => $startNode->node_id,
            'status' => BotSessionStatus::ACTIVE,
            'variables' => [], // Contact fields are accessed via contact.fieldName syntax
            'history' => [],
            'last_interaction_at' => now(),
            'timeout_at' => now()->addMinutes($bot->wait_time_minutes),
        ]);

        // Execute the first node
        $this->executeNode($session, $startNode);

        return $session;
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

        // Handle user response based on current node type
        if ($currentNode->type === BotNodeType::QUESTION_BUTTON) {
            // Store response in variable if configured
            if ($currentNode->variable_name) {
                $session->setVariable($currentNode->variable_name, $message->content);
            }

            // Find next node based on response
            $nextNode = $currentNode->getNextNode($message->content, $session->variables ?? []);

            if (! $nextNode && ! $currentNode->use_fallback) {
                // No match and no fallback - send no match message
                if ($session->bot->no_match_message) {
                    $this->sendMessage($session->conversation, $session->bot->no_match_message);
                }
                // Stay on current node
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
                $session->markAsCompleted();
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

    private function executeMessageNode(BotSession $session, BotNode $node): void
    {
        $content = $this->replaceVariables($node->content, $session);
        $this->sendMessage($session->conversation, $content);
    }

    private function executeTemplateNode(BotSession $session, BotNode $node): void
    {
        if (! $node->template_id) {
            $this->executeMessageNode($session, $node);

            return;
        }

        $conversation = $session->conversation;
        $phoneNumber = $conversation->phoneNumber;
        $waba = $conversation->waba;

        // Get template and prepare parameters with variable replacement
        $parameters = $node->template_parameters ?? [];
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
            'template_id' => $node->template_id,
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

    private function executeMediaNode(BotSession $session, BotNode $node): void
    {
        $conversation = $session->conversation;
        $phoneNumber = $conversation->phoneNumber;
        $waba = $conversation->waba;

        $messageType = match ($node->type) {
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
            'content' => $node->content ? $this->replaceVariables($node->content, $session) : null,
            'media' => [
                'url' => $node->media_url,
                'type' => $node->media_type,
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

    private function executeQuestionButtonNode(BotSession $session, BotNode $node): void
    {
        $conversation = $session->conversation;
        $phoneNumber = $conversation->phoneNumber;
        $waba = $conversation->waba;

        // Prepare interactive button message
        $content = $this->replaceVariables($node->content, $session);

        // WhatsApp allows maximum 3 buttons
        $buttons = [];
        if ($node->options && count($node->options) > 0) {
            foreach (array_slice($node->options, 0, 3) as $option) {
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

    private function executeAssignNode(BotSession $session, BotNode $node): void
    {
        $conversation = $session->conversation;

        if ($node->assign_type === 'user' && $node->assign_to_user_id) {
            $conversation->update(['assigned_user_id' => $node->assign_to_user_id]);
        } elseif ($node->assign_type === 'bot' && $node->assign_to_bot_id) {
            $newBot = Bot::find($node->assign_to_bot_id);
            if ($newBot) {
                $this->startBotSession($newBot, $conversation, $session->contact);
            }
        } else {
            // Unassign
            $conversation->update(['assigned_user_id' => null]);
        }
    }

    private function executeMarkAsSolvedNode(BotSession $session, BotNode $node): void
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

    private function executeConditionNode(BotSession $session, BotNode $node): void
    {
        $conditionMet = $node->evaluateCondition($session->variables ?? [], $session->contact);

        // Find the appropriate flow based on condition result
        $conditionPath = $conditionMet ? 'true' : 'false';

        $flow = $session->bot->flows()
            ->where('source_node_id', $node->node_id)
            ->where('condition_value', $conditionPath)
            ->first();

        if ($flow) {
            $nextNode = $session->bot->nodes()->where('node_id', $flow->target_node_id)->first();
            if ($nextNode) {
                $this->executeNode($session, $nextNode);
            } else {
                $session->markAsCompleted();
            }
        } else {
            // No flow defined for this condition result
            $session->markAsCompleted();
        }
    }

    private function executeStartAgainNode(BotSession $session, BotNode $node): void
    {
        // Simply jump back to the start node
        $startNode = $session->bot->getStartNode();

        if ($startNode) {
            // Just execute the start node again - keep all variables and history
            $this->executeNode($session, $startNode);
        }
    }

    private function executeLocationNode(BotSession $session, BotNode $node): void
    {
        if (! $node->latitude || ! $node->longitude) {
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
            'content' => $node->location_name ?? null,
            'location_data' => [
                'latitude' => $node->latitude,
                'longitude' => $node->longitude,
                'name' => $node->location_name,
                'address' => $node->location_address,
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

    private function executeWorkingHoursNode(BotSession $session, BotNode $node): void
    {
        // Check tenant-level working hours using session's tenant_id
        $tenantSettings = \App\Models\TenantSettings::where('tenant_id', $session->tenant_id)->first();

        // Default to available if no settings configured
        $isAvailable = true;
        if ($tenantSettings) {
            $isAvailable = $tenantSettings->isWithinWorkingHours();
        }

        $conditionPath = $isAvailable ? 'Available' : 'Unavailable';

        // Find the flow matching the availability path
        $flow = $session->bot->flows()
            ->where('source_node_id', $node->node_id)
            ->where('condition_value', $conditionPath)
            ->first();

        $nextNode = null;
        if ($flow) {
            $nextNode = $session->bot->nodes()->where('node_id', $flow->target_node_id)->first();
        }

        if ($nextNode) {
            $this->executeNode($session, $nextNode);
        } else {
            $session->markAsCompleted();
        }
    }

    private function executeSetVariableNode(BotSession $session, BotNode $node): void
    {
        $variables = $node->data['variables'] ?? null;

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
}
