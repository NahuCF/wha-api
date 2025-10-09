<?php

namespace App\Http\Controllers\Api;

use App\Enums\BotNodeType;
use App\Enums\FlowConditionType;
use App\Enums\FlowStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\BotFlowResource;
use App\Models\Bot;
use App\Models\BotEdge;
use App\Models\BotFlow;
use App\Models\BotNode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class BotFlowController extends Controller
{
    public function index(Request $request, Bot $bot)
    {
        $input = $request->validate([
            'rows_per_page' => ['sometimes', 'integer'],
            'search' => ['sometimes', 'string'],
        ]);

        $search = data_get($input, 'search');

        $flows = $bot->flows()
            ->with(['createdBy', 'updatedBy'])
            ->when($search, fn ($q) => $q->where('name', 'ILIKE', '%'.$search.'%'))
            ->orderBy('created_at', 'desc')
            ->get();

        return BotFlowResource::collection($flows);
    }

    public function store(Request $request, Bot $bot)
    {
        $input = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'nodes' => ['required', 'array'],
            'nodes.*.id' => ['required', 'string', 'distinct'],  
            'nodes.*.type' => ['required', Rule::in(BotNodeType::values())],
            'nodes.*.position' => ['required', 'array'],
            'nodes.*.position.x' => ['required', 'numeric'],
            'nodes.*.position.y' => ['required', 'numeric'],
            'nodes.*.data' => ['nullable', 'array'],
            'edges' => ['required', 'array'],
            'edges.*.id' => ['required', 'string', 'distinct'], 
            'edges.*.source' => ['required', 'string'],
            'edges.*.target' => ['required', 'string'],
            'edges.*.data' => ['nullable', 'array'],
            'viewport' => ['nullable', 'array'],
        ]);

        $name = data_get($input, 'name');
        $nodes = data_get($input, 'nodes', []);
        $edges = data_get($input, 'edges', []);
        $viewport = data_get($input, 'viewport');

        // Validate that edge sources and targets reference existing nodes
        $nodeIds = collect($nodes)->pluck('id')->toArray();
        $invalidEdges = [];
        
        foreach ($edges as $index => $edge) {
            $source = data_get($edge, 'source');
            $target = data_get($edge, 'target');
            
            if (!in_array($source, $nodeIds)) {
                $invalidEdges["edges.{$index}.source"] = ["Source node '{$source}' does not exist"];
            }
            if (!in_array($target, $nodeIds)) {
                $invalidEdges["edges.{$index}.target"] = ["Target node '{$target}' does not exist"];
            }
        }
        
        if (!empty($invalidEdges)) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $invalidEdges,
            ], 422);
        }

        $nameExists = $bot->flows()
            ->where('name', $name)
            ->exists();

        if ($nameExists) {
            return response()->json([
                'message' => 'A flow with this name already exists',
                'message_code' => 'flow_already_exists',
            ], 422);
        }

        $user = Auth::user();

        $flow = $bot->flows()->create([
            'name' => $name,
            'status' => FlowStatus::DRAFT,
            'user_id' => $user->id,
            'updated_user_id' => $user->id,
        ]);

        foreach ($nodes as $node) {
            // Validate user and bot IDs exist if provided
            $assignToUserId = data_get($node, 'data.assign_to_user_id');
            $assignToBotId = data_get($node, 'data.assign_to_bot_id');
            
            // Check if user exists in the tenant context
            if ($assignToUserId && !\App\Models\User::where('id', $assignToUserId)->exists()) {
                $assignToUserId = null;
            }
            
            // Check if bot exists in the tenant context
            if ($assignToBotId && !\App\Models\Bot::where('id', $assignToBotId)->exists()) {
                $assignToBotId = null;
            }
            
            BotNode::create([
                'bot_id' => $bot->id,
                'bot_flow_id' => $flow->id,
                'node_id' => data_get($node, 'id'),
                'type' => data_get($node, 'type'),
                'label' => data_get($node, 'data.label'),
                'position_x' => data_get($node, 'position.x'),
                'position_y' => data_get($node, 'position.y'),
                'content' => data_get($node, 'data.content'),
                'media_url' => data_get($node, 'data.media_url'),
                'media_type' => data_get($node, 'data.media_type'),
                'options' => data_get($node, 'data.options'),
                'variable_name' => data_get($node, 'data.variable_name'),
                'use_fallback' => data_get($node, 'data.use_fallback', false),
                'fallback_node_id' => data_get($node, 'data.fallback_node_id'),
                'header_type' => data_get($node, 'data.header_type'),
                'header_text' => data_get($node, 'data.header_text'),
                'header_media_url' => data_get($node, 'data.header_media_url'),
                'footer_text' => data_get($node, 'data.footer_text'),
                'assign_type' => data_get($node, 'data.assign_type'),
                'assign_to_user_id' => $assignToUserId,
                'assign_to_bot_id' => $assignToBotId,
                'latitude' => data_get($node, 'data.latitude'),
                'longitude' => data_get($node, 'data.longitude'),
                'location_name' => data_get($node, 'data.location_name'),
                'location_address' => data_get($node, 'data.location_address'),
                'template_id' => data_get($node, 'data.template_id'),
                'template_parameters' => data_get($node, 'data.template_parameters'),
                'conditions' => data_get($node, 'data.conditions'),
                'data' => data_get($node, 'data'),
            ]);
        }

        foreach ($edges as $edge) {
            $conditionType = data_get($edge, 'data.condition_type', FlowConditionType::ALWAYS->value);
            $conditionValue = data_get($edge, 'data.condition_value');

            BotEdge::create([
                'bot_id' => $bot->id,
                'bot_flow_id' => $flow->id,
                'edge_id' => data_get($edge, 'id'),
                'source_node_id' => data_get($edge, 'source'),
                'target_node_id' => data_get($edge, 'target'),
                'condition_type' => $conditionType,
                'condition_value' => $conditionValue,
            ]);
        }

        // Update bot viewport if provided
        if ($viewport) {
            $bot->update(['viewport' => $viewport]);
        }

        $flow->load(['createdBy', 'updatedBy']);

        return new BotFlowResource($flow);
    }

    public function activate(Bot $bot, BotFlow $flow)
    {
        if ($flow->bot_id !== $bot->id) {
            return response()->json(['message' => 'Flow not found'], 404);
        }

        if ($flow->nodes()->count() === 0) {
            return response()->json([
                'message' => 'Flow must have at least one node to be activated',
                'message_code' => 'flow_has_no_nodes',
            ], 422);
        }

        $flow->activate();

        return new BotFlowResource($flow->load(['createdBy', 'updatedBy']));
    }

    public function deactivate(Bot $bot, BotFlow $flow)
    {
        if ($flow->bot_id !== $bot->id) {
            return response()->json(['message' => 'Flow not found'], 404);
        }

        // Use the model's deactivate method
        $flow->deactivate();

        return new BotFlowResource($flow->load(['createdBy', 'updatedBy']));
    }

    public function update(Request $request, Bot $bot, BotFlow $flow)
    {
        if ($flow->bot_id !== $bot->id) {
            return response()->json(['message' => 'Flow not found'], 404);
        }

        $input = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'nodes' => ['sometimes', 'array'],
            'nodes.*.id' => ['required', 'string', 'distinct'], 
            'nodes.*.type' => ['required', Rule::in(BotNodeType::values())],
            'nodes.*.position' => ['required', 'array'],
            'nodes.*.position.x' => ['required', 'numeric'],
            'nodes.*.position.y' => ['required', 'numeric'],
            'nodes.*.data' => ['nullable', 'array'],
            'edges' => ['sometimes', 'array'],
            'edges.*.id' => ['required', 'string', 'distinct'],  
            'edges.*.source' => ['required', 'string'],
            'edges.*.target' => ['required', 'string'],
            'edges.*.data' => ['nullable', 'array'],
            'viewport' => ['nullable', 'array'],
        ]);

        $name = data_get($input, 'name');
        $nodes = data_get($input, 'nodes');
        $edges = data_get($input, 'edges');
        $viewport = data_get($input, 'viewport');

        if ($nodes && $edges) {
            $nodeIds = collect($nodes)->pluck('id')->toArray();
            $invalidEdges = [];
            
            foreach ($edges as $index => $edge) {
                $source = data_get($edge, 'source');
                $target = data_get($edge, 'target');
                
                if (!in_array($source, $nodeIds)) {
                    $invalidEdges["edges.{$index}.source"] = ["Source node '{$source}' does not exist"];
                }
                if (!in_array($target, $nodeIds)) {
                    $invalidEdges["edges.{$index}.target"] = ["Target node '{$target}' does not exist"];
                }
            }
            
            if (!empty($invalidEdges)) {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => $invalidEdges,
                ], 422);
            }
        }

        if ($name && ! $nodes && ! $edges) {
            $flow->update([
                'name' => $name,
                'updated_user_id' => Auth::user()->id,
            ]);

            return response()->json([
                'id' => $flow->id,
                'name' => $flow->name,
                'status' => $flow->status?->value,
                'updated_at' => $flow->updated_at,
            ]);
        }

        if (! $nodes || ! $edges) {
            return response()->json(['message' => 'Nodes and edges are required for flow update'], 422);
        }

        if ($flow->status === FlowStatus::ACTIVE && $flow->hasActiveSessions()) {
            return response()->json([
                'message' => 'Cannot update flow with active sessions',
                'active_sessions_count' => $flow->getActiveSessionsCount(),
            ], 422);
        }

        $flowWithName = BotFlow::query()
            ->where('name', $name)
            ->where('id', '!=', $flow->id)
            ->exists();

        if($flowWithName) {
            return response()->json([
                'message' => 'Flow name already exists',
                'message_code' => 'flow_already_exists',
            ], 422);
        }

        $user = Auth::user();

        if ($name) {
            $flow->update([
                'name' => $name,
                'updated_user_id' => $user->id,
            ]);
        } else {
            $flow->update(['updated_user_id' => $user->id]);
        }

        $flow->nodes()->delete();
        $flow->edges()->delete();

        foreach ($nodes as $node) {
            $assignToUserId = data_get($node, 'data.assign_to_user_id');
            $assignToBotId = data_get($node, 'data.assign_to_bot_id');
            
            if ($assignToUserId && !\App\Models\User::where('id', $assignToUserId)->exists()) {
                $assignToUserId = null;
            }
            
            if ($assignToBotId && !\App\Models\Bot::where('id', $assignToBotId)->exists()) {
                $assignToBotId = null;
            }
            
            BotNode::create([
                'bot_id' => $bot->id,
                'bot_flow_id' => $flow->id,
                'node_id' => data_get($node, 'id'),
                'type' => data_get($node, 'type'),
                'label' => data_get($node, 'data.label'),
                'position_x' => data_get($node, 'position.x'),
                'position_y' => data_get($node, 'position.y'),
                'content' => data_get($node, 'data.content'),
                'media_url' => data_get($node, 'data.media_url'),
                'media_type' => data_get($node, 'data.media_type'),
                'options' => data_get($node, 'data.options'),
                'variable_name' => data_get($node, 'data.variable_name'),
                'use_fallback' => data_get($node, 'data.use_fallback', false),
                'fallback_node_id' => data_get($node, 'data.fallback_node_id'),
                'header_type' => data_get($node, 'data.header_type'),
                'header_text' => data_get($node, 'data.header_text'),
                'header_media_url' => data_get($node, 'data.header_media_url'),
                'footer_text' => data_get($node, 'data.footer_text'),
                'assign_type' => data_get($node, 'data.assign_type'),
                'assign_to_user_id' => $assignToUserId,
                'assign_to_bot_id' => $assignToBotId,
                'latitude' => data_get($node, 'data.latitude'),
                'longitude' => data_get($node, 'data.longitude'),
                'location_name' => data_get($node, 'data.location_name'),
                'location_address' => data_get($node, 'data.location_address'),
                'template_id' => data_get($node, 'data.template_id'),
                'template_parameters' => data_get($node, 'data.template_parameters'),
                'conditions' => data_get($node, 'data.conditions'),
                'data' => data_get($node, 'data'),
            ]);
        }

        // Create edges
        foreach ($edges as $edge) {
            // Determine condition type from edge or source node
            $conditionType = data_get($edge, 'data.condition_type', FlowConditionType::ALWAYS->value);
            $conditionValue = data_get($edge, 'data.condition_value');

            BotEdge::create([
                'bot_id' => $bot->id,
                'bot_flow_id' => $flow->id,
                'edge_id' => data_get($edge, 'id'),
                'source_node_id' => data_get($edge, 'source'),
                'target_node_id' => data_get($edge, 'target'),
                'condition_type' => $conditionType,
                'condition_value' => $conditionValue,
            ]);
        }

        if ($viewport) {
            $bot->update(['viewport' => $viewport]);
        }

        $flow->load(['createdBy', 'updatedBy']);

        return new BotFlowResource($flow);
    }

    public function destroy(Bot $bot, BotFlow $flow)
    {
        if ($flow->bot_id !== $bot->id) {
            return response()->json(['message' => 'Flow not found'], 404);
        }

        if ($flow->status === FlowStatus::ACTIVE && $flow->hasActiveSessions()) {
            return response()->json([
                'message' => 'Cannot delete flow with active sessions',
                'message_code' => 'flow_has_active_sessions',
            ], 422);
        }

        if ($flow->status === FlowStatus::ACTIVE) {
            $anotherFlow = $bot->flows()
                ->where('id', '!=', $flow->id)
                ->first();

            if ($anotherFlow) {
                $anotherFlow->activate();
            }
        }

        $flow->delete();

        return response()->noContent();
    }

    public function flowData(Bot $bot, BotFlow $flow)
    {
        if ($flow->bot_id !== $bot->id) {
            return response()->json(['message' => 'Flow not found'], 404);
        }

        $flow->load(['nodes', 'edges', 'createdBy', 'updatedBy']);

        $nodes = $flow->nodes->map(function ($node) {
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

        $edges = $flow->edges->map(function ($edge) {
            $data = [];
            
            if ($edge->condition_type !== FlowConditionType::ALWAYS) {
                $data['condition_type'] = $edge->condition_type->value;
            }
            
            if ($edge->condition_value) {
                $data['condition_value'] = $edge->condition_value;
            }
            
            return [
                'id' => $edge->edge_id,
                'source' => $edge->source_node_id,
                'target' => $edge->target_node_id,
                'data' => !empty($data) ? $data : null,
            ];
        });

        return response()->json([
            'id' => $flow->id,
            'name' => $flow->name,
            'status' => $flow->status->value,
            'nodes' => $nodes,
            'edges' => $edges,
            'viewport' => $bot->viewport ?? ['x' => 0, 'y' => 0, 'zoom' => 1],
            'created_at' => $flow->created_at,
            'updated_at' => $flow->updated_at,
            'created_by' => $flow->createdBy,
            'updated_by' => $flow->updatedBy 
        ]);
    }

    public function destroyFlow(Bot $bot, BotFlow $flow)
    {
        if ($flow->bot_id !== $bot->id) {
            return response()->json(['message' => 'Flow not found'], 404);
        }

        if ($bot->flows()->count() <= 1) {
            return response()->json(['message' => 'Bot must have at least one flow'], 422);
        }

        // Prevent deletion of active flow with sessions
        if ($flow->status === FlowStatus::ACTIVE && $flow->hasActiveSessions()) {
            return response()->json([
                'message' => 'Cannot delete flow with active sessions',
                'message_code' => 'flow_has_active_sessions',
                'active_sessions_count' => $flow->getActiveSessionsCount(),
            ], 422);
        }

        $flow->delete();

        return response()->noContent();
    }
}
