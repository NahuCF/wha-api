<?php

namespace App\Http\Controllers\Api;

use App\Enums\BotAction;
use App\Enums\BotKeywordMatchType;
use App\Enums\BotNodeType;
use App\Enums\BotTriggerType;
use App\Enums\FlowConditionType;
use App\Http\Controllers\Controller;
use App\Http\Resources\BotResource;
use App\Models\Bot;
use App\Models\BotFlow;
use App\Models\BotNode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class BotController extends Controller
{
    public function index(Request $request)
    {
        $input = $request->validate([
            'rows_per_page' => ['sometimes', 'integer'],
            'search' => ['sometimes', 'string'],
        ]);

        $rowsPerPage = data_get($input, 'rows_per_page', 20);
        $search = data_get($input, 'search');

        $bots = Bot::query()
            ->with(['createdBy', 'updatedBy', 'flows'])
            ->when($search, fn ($q) => $q->where('name', 'ILIKE', '%'.$search.'%'))
            ->paginate($rowsPerPage);

        return BotResource::collection($bots);
    }

    public function store(Request $request)
    {
        $input = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'trigger_type' => ['required', Rule::in(BotTriggerType::values())],
            'keywords' => ['required_if:trigger_type,keyword', 'array'],
            'keywords.*.keyword' => ['required', 'string'],
            'keywords.*.match_type' => ['required', Rule::in(BotKeywordMatchType::values())],
            'keywords.*.case_sensitive' => ['nullable', 'boolean'],
        ]);

        $name = data_get($input, 'name');
        $triggerType = data_get($input, 'trigger_type');
        $keywords = data_get($input, 'keywords');

        $user = Auth::user();

        $botExist = Bot::query()
            ->where('name', $name)
            ->exists();

        if ($botExist) {
            return response()->json([
                'message' => 'Bot already exists',
                'message_code' => 'bot_already_exists',
            ], 422);
        }

        $bot = Bot::create([
            'name' => $name,
            'user_id' => $user->id,
            'updated_user_id' => $user->id,
            'trigger_type' => $triggerType,
            'keywords' => $keywords,
        ]);

        $bot->load(['createdBy', 'updatedBy']);

        return new BotResource($bot);
    }

    public function show(Bot $bot)
    {
        return new BotResource($bot->load(['createdBy', 'updatedBy', 'flows']));
    }

    public function update(Request $request, Bot $bot)
    {
        $input = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'trigger_type' => ['sometimes', Rule::in(BotTriggerType::values())],
            'keywords' => ['nullable', 'array'],
            'keywords.*.keyword' => ['required', 'string'],
            'keywords.*.match_type' => ['required', Rule::in(BotKeywordMatchType::values())],
            'keywords.*.case_sensitive' => ['nullable', 'boolean'],
        ]);

        $user = Auth::user();
        $input['updated_user_id'] = $user->id;

        $bot->update($input);

        return new BotResource($bot->load(['createdBy', 'updatedBy', 'flows']));
    }

    public function destroy(Bot $bot)
    {
        if ($bot->hasActiveSessions()) {
            return response()->json([
                'message' => 'Cannot delete bot with active sessions',
                'message_code' => 'bot_has_active_sessions',
            ], 422);
        }

        $bot->delete();

        return response()->noContent();
    }

    public function updateConfiguration(Request $request, Bot $bot)
    {
        $input = $request->validate([
            'is_active' => ['boolean'],
            'wait_time_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'timeout_action' => ['nullable', Rule::in(BotAction::values())],
            'timeout_assign_bot_id' => ['nullable', 'exists:bots,id'],
            'timeout_assign_user_id' => ['nullable', 'exists:users,id'],
            'timeout_message' => ['nullable', 'string'],
            'no_match_message' => ['nullable', 'string'],
            'no_match_action' => ['nullable', Rule::in(BotAction::values())],
            'no_match_assign_bot_id' => ['nullable', 'exists:bots,id'],
            'no_match_assign_user_id' => ['nullable', 'exists:users,id'],
            'end_conversation_action' => ['nullable', Rule::in(BotAction::values())],
            'end_conversation_message' => ['nullable', 'string'],
            'end_conversation_assign_bot_id' => ['nullable', 'exists:bots,id'],
            'end_conversation_assign_user_id' => ['nullable', 'exists:users,id'],
        ]);

        $isActive = data_get($input, 'is_active');
        $waitTimeMinutes = data_get($input, 'wait_time_minutes');
        $timeoutAction = data_get($input, 'timeout_action');
        $timeoutAssignBotId = data_get($input, 'timeout_assign_bot_id');
        $timeoutAssignUserId = data_get($input, 'timeout_assign_user_id');
        $timeoutMessage = data_get($input, 'timeout_message');
        $noMatchMessage = data_get($input, 'no_match_message');
        $noMatchAction = data_get($input, 'no_match_action');
        $noMatchAssignBotId = data_get($input, 'no_match_assign_bot_id');
        $noMatchAssignUserId = data_get($input, 'no_match_assign_user_id');
        $endConversationAction = data_get($input, 'end_conversation_action');
        $endConversationMessage = data_get($input, 'end_conversation_message');
        $endConversationAssignBotId = data_get($input, 'end_conversation_assign_bot_id');
        $endConversationAssignUserId = data_get($input, 'end_conversation_assign_user_id');

        $bot->update([
            'is_active' => $isActive,
            'wait_time_minutes' => $waitTimeMinutes,
            'timeout_action' => $timeoutAction,
            'timeout_assign_bot_id' => $timeoutAssignBotId,
            'timeout_assign_user_id' => $timeoutAssignUserId,
            'timeout_message' => $timeoutMessage,
            'no_match_message' => $noMatchMessage,
            'no_match_action' => $noMatchAction,
            'no_match_assign_bot_id' => $noMatchAssignBotId,
            'no_match_assign_user_id' => $noMatchAssignUserId,
            'end_conversation_action' => $endConversationAction,
            'end_conversation_message' => $endConversationMessage,
            'end_conversation_assign_bot_id' => $endConversationAssignBotId,
            'end_conversation_assign_user_id' => $endConversationAssignUserId,
        ]);

        return new BotResource($bot->load(['flows']));
    }

    public function saveFlow(Request $request, Bot $bot)
    {
        $input = $request->validate([
            'nodes' => ['required', 'array'],
            'nodes.*.id' => ['required', 'string'],
            'nodes.*.type' => ['required', Rule::in(BotNodeType::values())],
            'nodes.*.position' => ['required', 'array'],
            'nodes.*.position.x' => ['required', 'numeric'],
            'nodes.*.position.y' => ['required', 'numeric'],
            'nodes.*.data' => ['nullable', 'array'],
            'edges' => ['required', 'array'],
            'edges.*.id' => ['required', 'string'],
            'edges.*.source' => ['required', 'string'],
            'edges.*.target' => ['required', 'string'],
            'edges.*.sourceHandle' => ['nullable', 'string'],
            'edges.*.targetHandle' => ['nullable', 'string'],
            'viewport' => ['nullable', 'array'],
        ]);

        $nodes = data_get($input, 'nodes', []);
        $edges = data_get($input, 'edges', []);
        $viewport = data_get($input, 'viewport');

        $bot->nodes()->delete();
        $bot->flows()->delete();

        foreach ($nodes as $node) {
            BotNode::create([
                'bot_id' => $bot->id,
                'node_id' => data_get($node, 'id'),
                'type' => data_get($node, 'type'),
                'label' => data_get($node, 'data.label'),
                'position_x' => data_get($node, 'position.x'),
                'position_y' => data_get($node, 'position.y'),
                'data' => data_get($node, 'data', []),
                'content' => data_get($node, 'data.content'),
                'media_url' => data_get($node, 'data.media_url'),
                'media_type' => data_get($node, 'data.media_type'),
                'options' => data_get($node, 'data.options'),
                'variable_name' => data_get($node, 'data.variable_name'),
                'use_fallback' => data_get($node, 'data.use_fallback', false),
                'fallback_node_id' => data_get($node, 'data.fallback_node_id'),
                // Header fields for question_button nodes
                'header_type' => data_get($node, 'data.header_type'),
                'header_text' => data_get($node, 'data.header_text'),
                'header_media_url' => data_get($node, 'data.header_media_url'),
                'footer_text' => data_get($node, 'data.footer_text'),
                'assign_type' => data_get($node, 'data.assign_type'),
                'assign_to_user_id' => data_get($node, 'data.assign_to_user_id'),
                'assign_to_bot_id' => data_get($node, 'data.assign_to_bot_id'),
                'latitude' => data_get($node, 'data.latitude'),
                'longitude' => data_get($node, 'data.longitude'),
                'location_name' => data_get($node, 'data.location_name'),
                'location_address' => data_get($node, 'data.location_address'),
                // Conditions array (AND logic)
                'conditions' => data_get($node, 'data.conditions', []),
                // Template node fields
                'template_id' => data_get($node, 'data.template_id'),
                'template_parameters' => data_get($node, 'data.template_parameters'),
            ]);
        }

        foreach ($edges as $edge) {
            $sourceNode = collect($nodes)->firstWhere('id', data_get($edge, 'source'));
            $sourceNodeType = data_get($sourceNode, 'type');

            $conditionType = FlowConditionType::ALWAYS;
            $conditionValue = null;

            if ($sourceNodeType === BotNodeType::QUESTION_BUTTON->value) {
                $conditionType = FlowConditionType::OPTION;
                $conditionValue = data_get($edge, 'data.option_id');

                if (data_get($edge, 'data.is_default')) {
                    $conditionType = FlowConditionType::DEFAULT;
                    $conditionValue = null;
                }
            } elseif ($sourceNodeType === BotNodeType::CONDITION->value) {
                if (data_get($edge, 'data.condition_path') === 'true') {
                    $conditionType = FlowConditionType::ALWAYS;
                    $conditionValue = 'true';
                } else {
                    $conditionType = FlowConditionType::ALWAYS;
                    $conditionValue = 'false';
                }
            } elseif ($sourceNodeType === BotNodeType::WORKING_HOURS->value) {
                $conditionType = FlowConditionType::ALWAYS;
                $conditionValue = data_get($edge, 'data.working_hours_path', 'Available');
            }

            BotFlow::create([
                'bot_id' => $bot->id,
                'edge_id' => data_get($edge, 'id'),
                'source_node_id' => data_get($edge, 'source'),
                'target_node_id' => data_get($edge, 'target'),
                'condition_type' => $conditionType,
                'condition_value' => $conditionValue,
            ]);
        }
        if ($viewport) {
            $bot->update([
                'viewport' => $viewport,
            ]);
        }

        return new BotResource($bot->load(['flows']));
    }

    public function getFlowData(Bot $bot)
    {
        $nodes = $bot->nodes->map(function ($node) {
            return [
                'id' => $node->node_id,
                'type' => $node->type->value,
                'position' => [
                    'x' => $node->position_x,
                    'y' => $node->position_y,
                ],
                'data' => array_merge(
                    [
                        'label' => $node->label,
                        'content' => $node->content,
                        'media_url' => $node->media_url,
                        'media_type' => $node->media_type,
                        'options' => $node->options,
                        'variable_name' => $node->variable_name,
                        'use_fallback' => $node->use_fallback,
                        'fallback_node_id' => $node->fallback_node_id,
                        'header_type' => $node->header_type,
                        'header_text' => $node->header_text,
                        'header_media_url' => $node->header_media_url,
                        'footer_text' => $node->footer_text,
                        'assign_type' => $node->assign_type,
                        'assign_to_user_id' => $node->assign_to_user_id,
                        'assign_to_bot_id' => $node->assign_to_bot_id,
                        'latitude' => $node->latitude,
                        'longitude' => $node->longitude,
                        'location_name' => $node->location_name,
                        'location_address' => $node->location_address,
                        'template_id' => $node->template_id,
                        'template_parameters' => $node->template_parameters,
                        'conditions' => $node->conditions,
                    ],
                    $node->data ?? []
                ),
            ];
        });

        $edges = $bot->flows->map(function ($flow) {
            return [
                'id' => $flow->edge_id,
                'source' => $flow->source_node_id,
                'target' => $flow->target_node_id,
                'sourceHandle' => $flow->source_handle,
                'targetHandle' => $flow->target_handle,
                'data' => $flow->data,
            ];
        });

        return response()->json([
            'nodes' => $nodes,
            'edges' => $edges,
            'viewport' => $bot->viewport ?? ['x' => 0, 'y' => 0, 'zoom' => 1],
        ]);
    }

    public function uploadNodeMedia(Request $request, Bot $bot)
    {
        $input = $request->validate([
            'file' => ['required', 'file', 'max:16384'], // 16MB max
            'media_type' => ['required', Rule::in(['image', 'video', 'audio', 'document'])],
            'node_id' => ['required', 'string'],
        ]);

        $file = $request->file('file');
        $mediaType = data_get($input, 'media_type');
        $nodeId = data_get($input, 'node_id');

        $allowedMimes = match ($mediaType) {
            'image' => ['image/jpeg', 'image/png'],
            'video' => ['video/mp4', 'video/3gpp'],
            'audio' => ['audio/aac', 'audio/mp4', 'audio/mpeg', 'audio/amr', 'audio/ogg'],
            'document' => ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'text/plain'],
            default => []
        };

        $maxSize = match ($mediaType) {
            'image' => 5 * 1024, // 5MB
            'video' => 16 * 1024, // 16MB
            'audio' => 16 * 1024, // 16MB
            'document' => 50 * 1024, // 100MB
            default => 16 * 1024
        };

        if (! in_array($file->getMimeType(), $allowedMimes)) {
            return response()->json([
                'message' => 'Invalid file type for '.$mediaType,
                'allowed_types' => $allowedMimes,
            ], 422);
        }

        if ($file->getSize() > $maxSize * 1024) {
            return response()->json([
                'message' => 'File size exceeds limit',
                'max_size_mb' => $maxSize / 1024,
            ], 422);
        }

        try {
            $extension = $file->getClientOriginalExtension();
            $filename = uniqid('bot_'.$bot->id.'_node_'.$nodeId.'_').'.'.$extension;

            $path = 'bot-media/'.tenant('id').'/'.$bot->id.'/'.$mediaType.'/'.$filename;

            $s3Path = Storage::disk('s3')->putFileAs('', $file, $path);

            if (! $s3Path) {
                throw new \Exception('Failed to upload file to S3');
            }

            $url = Storage::disk('s3')->url($s3Path);

            return response()->json([
                'url' => $url,
                'path' => $s3Path,
                'media_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'filename' => $file->getClientOriginalName(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Upload failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function deleteNodeMedia(Request $request, Bot $bot)
    {
        $input = $request->validate([
            'path' => ['required', 'string'],
        ]);

        $path = data_get($input, 'path');

        try {
            $deleted = Storage::disk('s3')->delete($path);

            if (! $deleted) {
                throw new \Exception('Failed to delete file from S3');
            }

            return response()->json([
                'message' => 'Media deleted successfully',
                'path' => $path,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Delete failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function clone(Request $request, Bot $bot)
    {
        $input = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $newName = data_get($input, 'name');
        $user = Auth::user();

        // Check if bot with new name already exists
        $botExists = Bot::where('name', $newName)->exists();
        if ($botExists) {
            return response()->json([
                'message' => 'Bot with this name already exists',
                'message_code' => 'bot_already_exists',
            ], 422);
        }

        // Clone the bot
        $newBot = $bot->replicate();
        $newBot->name = $newName;
        $newBot->user_id = $user->id;
        $newBot->updated_user_id = $user->id;
        $newBot->status = \App\Enums\BotStatus::DRAFT; // Start with draft status
        $newBot->created_at = now();
        $newBot->updated_at = now();
        $newBot->save();

        // Clone all nodes
        $nodeMapping = []; // Map old node IDs to new node IDs
        foreach ($bot->nodes as $node) {
            $newNode = $node->replicate();
            $newNode->bot_id = $newBot->id;
            $newNode->created_at = now();
            $newNode->updated_at = now();
            $newNode->save();

            // Store mapping of old to new node IDs
            $nodeMapping[$node->node_id] = $newNode->node_id;
        }

        // Clone all flows/edges with updated node references
        foreach ($bot->flows as $flow) {
            $newFlow = $flow->replicate();
            $newFlow->bot_id = $newBot->id;

            // Update source and target node IDs if they exist in mapping
            if (isset($nodeMapping[$flow->source_node_id])) {
                $newFlow->source_node_id = $nodeMapping[$flow->source_node_id];
            }
            if (isset($nodeMapping[$flow->target_node_id])) {
                $newFlow->target_node_id = $nodeMapping[$flow->target_node_id];
            }

            $newFlow->created_at = now();
            $newFlow->updated_at = now();
            $newFlow->save();
        }

        // Load relationships for the response
        $newBot->load(['flows']);

        return response()->json([
            'message' => 'Bot cloned successfully',
            'data' => new BotResource($newBot),
        ], 201);
    }

    public function activate(Bot $bot)
    {
        if ($bot->tenant_id !== tenant('id')) {
            return response()->json([
                'message' => 'Bot not found',
                'message_code' => 'bot_not_found',
            ], 404);
        }

        if ($bot->nodes()->count() === 0) {
            return response()->json([
                'message' => 'Bot must have at least one node to be activated',
                'message_code' => 'bot_has_no_nodes',
            ], 422);
        }

        $bot->status = \App\Enums\BotStatus::ACTIVE;
        $bot->save();

        $bot->load(['createdBy', 'updatedBy']);

        return BotResource::make($bot);
    }

    public function deactivate(Bot $bot)
    {
        if ($bot->tenant_id !== tenant('id')) {
            return response()->json([
                'message' => 'Bot not found',
                'message_code' => 'bot_not_found',
            ], 404);
        }

        $bot->status = \App\Enums\BotStatus::DRAFT;
        $bot->save();

        \App\Models\BotSession::where('bot_id', $bot->id)
            ->whereIn('status', [\App\Enums\BotSessionStatus::ACTIVE, \App\Enums\BotSessionStatus::WAITING])
            ->update(['status' => \App\Enums\BotSessionStatus::COMPLETED]);

        $bot->load(['createdBy', 'updatedBy']);

        return BotResource::make($bot);
    }

    public function checkActiveSessions(Bot $bot)
    {
        if ($bot->tenant_id !== tenant('id')) {
            return response()->json([
                'message' => 'Bot not found',
                'message_code' => 'bot_not_found',
            ], 404);
        }

        $activeSessions = \App\Models\BotSession::where('bot_id', $bot->id)
            ->whereIn('status', [\App\Enums\BotSessionStatus::ACTIVE, \App\Enums\BotSessionStatus::WAITING])
            ->with(['conversation.waba'])
            ->get();

        $totalActiveSessions = $activeSessions->count();

        $wabas = $activeSessions
            ->pluck('conversation.waba.name')
            ->filter()
            ->unique()
            ->values();

        return response()->json([
            'total_active_sessions' => $totalActiveSessions,
            'has_active_sessions' => $totalActiveSessions > 0,
            'waba_names' => $wabas,
        ]);
    }
}
