<?php

namespace App\Models;

use App\Enums\BotNodeType;
use App\Enums\ComparisonOperator;
use App\Enums\FlowConditionType;
use App\Models\BotVariable;
use App\Models\Contact;
use App\Services\BotVariableInterpolator;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BotNode extends Model
{
    use HasUlids;


    protected $casts = [
        'position_x' => 'float',
        'position_y' => 'float',
        'data' => 'array',
        'options' => 'array',
        'use_fallback' => 'boolean',
        'type' => BotNodeType::class,
        'latitude' => 'float',
        'longitude' => 'float',
        'template_parameters' => 'array',
        'conditions' => 'array',
    ];

    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    public function assignToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assign_to_user_id');
    }

    public function assignToBot(): BelongsTo
    {
        return $this->belongsTo(Bot::class, 'assign_to_bot_id');
    }

    public function fallbackNode(): BelongsTo
    {
        return $this->belongsTo(BotNode::class, 'fallback_node_id');
    }


    public function outgoingFlows(): HasMany
    {
        return $this->hasMany(BotFlow::class, 'source_node_id', 'node_id')
            ->where('bot_id', $this->bot_id);
    }

    public function incomingFlows(): HasMany
    {
        return $this->hasMany(BotFlow::class, 'target_node_id', 'node_id')
            ->where('bot_id', $this->bot_id);
    }

    public function getNextNode($userInput = null, $sessionVariables = []): ?BotNode
    {
        if ($this->type === BotNodeType::CONDITION) {
            return $this->getNextNodeForCondition($sessionVariables);
        }
        
        $flows = $this->outgoingFlows()->get();
        
        foreach ($flows as $flow) {
            // Check flow condition
            if ($this->evaluateFlowCondition($flow, $userInput)) {
                return $this->bot->nodes()
                    ->where('node_id', $flow->target_node_id)
                    ->first();
            }
        }

        if ($this->type === BotNodeType::QUESTION_BUTTON && $this->use_fallback && $this->fallback_node_id) {
            return $this->fallbackNode;
        }

        return null;
    }
    
    private function getNextNodeForCondition($sessionVariables): ?BotNode
    {
        $conditionMet = $this->evaluateCondition($sessionVariables);
        
        $flows = $this->outgoingFlows()->get();
        
        foreach ($flows as $flow) {
            // Check condition_value to determine which path (true/false)
            if ($flow->condition_value === 'true' && $conditionMet) {
                return $this->bot->nodes()
                    ->where('node_id', $flow->target_node_id)
                    ->first();
            } elseif ($flow->condition_value === 'false' && !$conditionMet) {
                return $this->bot->nodes()
                    ->where('node_id', $flow->target_node_id)
                    ->first();
            }
        }
        
        return null;
    }
    
    public function evaluateCondition($sessionVariables, ?Contact $contact = null): bool
    {
        if ($this->type !== BotNodeType::CONDITION) {
            return false;
        }
        
        return $this->evaluateMultipleConditions($sessionVariables, $contact);
    }
    
    /**
     * Evaluate conditions with AND logic
     */
    private function evaluateMultipleConditions($sessionVariables, ?Contact $contact = null): bool
    {
        if (empty($this->conditions)) {
            return false;
        }
        
        $interpolator = new BotVariableInterpolator($contact, $sessionVariables);
        
        foreach ($this->conditions as $condition) {
            $variableId = $condition['variable_id'] ?? null;
            $operator = $condition['operator'] ?? null;
            $value = $condition['value'] ?? null;
            $valueVariableId = $condition['value_variable_id'] ?? null;
            
            if (!$variableId) {
                continue; 
            }
            
            $variable = BotVariable::find($variableId);
            if (!$variable) {
                return false; 
            }
            
            $leftValue = $sessionVariables[$variable->name] ?? null;
            
            if ($valueVariableId) {
                $rightVariable = BotVariable::find($valueVariableId);
                $rightValue = $rightVariable ? ($sessionVariables[$rightVariable->name] ?? null) : null;
            } else {
                $rightValue = $value ? $interpolator->interpolate($value) : $value;
            }
            
            try {
                $operatorEnum = ComparisonOperator::from($operator);
                if (!$operatorEnum->evaluate($leftValue, $rightValue)) {
                    return false; 
                }
            } catch (\ValueError $e) {
                return false; 
            }
        }
        
        return true; // All conditions passed
    }
    
    private function evaluateFlowCondition($flow, $userInput = null): bool
    {
        switch ($flow->condition_type) {
            case FlowConditionType::ALWAYS:
                return true;
                
            case FlowConditionType::OPTION:
                if ($this->type === BotNodeType::QUESTION_BUTTON && $userInput !== null) {
                    return $userInput === $flow->condition_value;
                }
                return false;
                
            case FlowConditionType::DEFAULT:
                return true;
                
            default:
                return false;
        }
    }
    
    /**
     * Get interpolated content with variables replaced
     */
    public function getInterpolatedContent(?Contact $contact = null, array $sessionVariables = []): ?string
    {
        if (!$this->content) {
            return null;
        }
        
        $interpolator = new BotVariableInterpolator($contact, $sessionVariables);
        return $interpolator->interpolate($this->content);
    }
    
    /**
     * Get interpolated options for question_button nodes
     */
    public function getInterpolatedOptions(?Contact $contact = null, array $sessionVariables = []): ?array
    {
        if ($this->type !== BotNodeType::QUESTION_BUTTON || !$this->options) {
            return $this->options;
        }
        
        $interpolator = new BotVariableInterpolator($contact, $sessionVariables);
        return $interpolator->interpolateOptions($this->options);
    }
    
    /**
     * Get interpolated header text
     */
    public function getInterpolatedHeaderText(?Contact $contact = null, array $sessionVariables = []): ?string
    {
        if (!$this->header_text) {
            return null;
        }
        
        $interpolator = new BotVariableInterpolator($contact, $sessionVariables);
        return $interpolator->interpolate($this->header_text);
    }
    
    /**
     * Get interpolated footer text
     */
    public function getInterpolatedFooterText(?Contact $contact = null, array $sessionVariables = []): ?string
    {
        if (!$this->footer_text) {
            return null;
        }
        
        $interpolator = new BotVariableInterpolator($contact, $sessionVariables);
        return $interpolator->interpolate($this->footer_text);
    }
    
    /**
     * Get fully processed node data with all interpolations
     */
    public function getProcessedData(?Contact $contact = null, array $sessionVariables = []): array
    {
        $interpolator = new BotVariableInterpolator($contact, $sessionVariables);
        return $interpolator->processNode($this);
    }

}
