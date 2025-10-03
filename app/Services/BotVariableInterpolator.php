<?php

namespace App\Services;

use App\Models\BotNode;
use App\Models\Contact;

class BotVariableInterpolator
{
    private ?Contact $contact;

    private array $sessionVariables;

    public function __construct(?Contact $contact = null, array $sessionVariables = [])
    {
        $this->contact = $contact;
        $this->sessionVariables = $sessionVariables;
    }

    /**
     * Set or update the contact
     */
    public function setContact(?Contact $contact): self
    {
        $this->contact = $contact;

        return $this;
    }

    /**
     * Set or update session variables
     */
    public function setSessionVariables(array $sessionVariables): self
    {
        $this->sessionVariables = $sessionVariables;

        return $this;
    }

    /**
     * Add a single session variable
     */
    public function addSessionVariable(string $name, $value): self
    {
        $this->sessionVariables[$name] = $value;

        return $this;
    }

    /**
     * Interpolate variables in text content
     * Supports:
     * - {{contact.field_name}} - Contact fields
     * - {{variable_name}} - Bot session variables
     */
    public function interpolate(string $text): string
    {
        // First, replace contact fields
        if ($this->contact) {
            $text = $this->interpolateContactFields($text);
        }

        // Then, replace bot variables
        $text = $this->interpolateBotVariables($text);

        return $text;
    }

    /**
     * Interpolate an array of options (for question_button nodes)
     */
    public function interpolateOptions(array $options): array
    {
        return array_map(function ($option) {
            if (isset($option['title'])) {
                $option['title'] = $this->interpolate($option['title']);
            }

            return $option;
        }, $options);
    }

    /**
     * Interpolate contact fields in the format {{contact.field_name}}
     */
    private function interpolateContactFields(string $text): string
    {
        // Load field values with their field definitions if not already loaded
        $this->contact->loadMissing('fieldValues.field');

        // Match patterns like {{contact.Name}}, {{contact.email}}, etc.
        return preg_replace_callback('/\{\{contact\.(\w+)\}\}/i', function ($matches) {
            $fieldName = $matches[1];

            // Search through field values
            foreach ($this->contact->fieldValues as $fieldValue) {
                // Check if field name matches (case-insensitive)
                if ($fieldValue->field && strcasecmp($fieldValue->field->name, $fieldName) === 0) {
                    // Handle different field types
                    $value = $fieldValue->value;

                    // If value is an array or object, convert to string
                    if (is_array($value) || is_object($value)) {
                        return json_encode($value);
                    }

                    return (string) $value;
                }
            }

            // Return empty string if field not found
            return '';
        }, $text);
    }

    /**
     * Interpolate bot variables in the format {{variable_name}}
     */
    private function interpolateBotVariables(string $text): string
    {
        // Match patterns like {{variable_name}} (but not {{contact.something}})
        return preg_replace_callback('/\{\{(?!contact\.)(\w+)\}\}/', function ($matches) {
            $variableName = $matches[1];

            return $this->sessionVariables[$variableName] ?? '';
        }, $text);
    }

    /**
     * Extract all variable names from a text string
     * Returns an array of variable names that need to be resolved
     */
    public function extractVariableNames(string $text): array
    {
        $variables = [
            'contact_fields' => [],
            'bot_variables' => [],
        ];

        // Extract contact fields
        preg_match_all('/\{\{contact\.(\w+)\}\}/i', $text, $contactMatches);
        if (! empty($contactMatches[1])) {
            $variables['contact_fields'] = array_unique($contactMatches[1]);
        }

        // Extract bot variables (excluding contact.*)
        preg_match_all('/\{\{(?!contact\.)(\w+)\}\}/', $text, $botMatches);
        if (! empty($botMatches[1])) {
            $variables['bot_variables'] = array_unique($botMatches[1]);
        }

        return $variables;
    }

    /**
     * Process a complete bot node with all interpolations
     * This is the main service method for processing interactive messages
     */
    public function processNode(BotNode $node): array
    {
        $processedData = [
            'type' => $node->type->value,
            'node_id' => $node->node_id,
        ];

        // Process content (body)
        if ($node->content) {
            $processedData['content'] = $this->interpolate($node->content);
        }

        // Process header if present
        if ($node->header_type) {
            $processedData['header'] = [
                'type' => $node->header_type,
            ];

            if ($node->header_type === 'text' && $node->header_text) {
                $processedData['header']['text'] = $this->interpolate($node->header_text);
            } elseif (in_array($node->header_type, ['image', 'video', 'document']) && $node->header_media_url) {
                $processedData['header']['media_url'] = $node->header_media_url;
            }
        }

        // Process footer if present
        if ($node->footer_text) {
            $processedData['footer'] = $this->interpolate($node->footer_text);
        }

        // Process options (buttons) if present
        if ($node->options) {
            $processedData['options'] = $this->interpolateOptions($node->options);
        }

        // Include other relevant fields
        if ($node->media_url) {
            $processedData['media_url'] = $node->media_url;
        }

        if ($node->media_type) {
            $processedData['media_type'] = $node->media_type;
        }

        // Include location data if present
        if ($node->latitude && $node->longitude) {
            $processedData['location'] = [
                'latitude' => $node->latitude,
                'longitude' => $node->longitude,
                'name' => $node->location_name,
                'address' => $node->location_address,
            ];
        }

        // Include template data if present
        if ($node->template_id) {
            $processedData['template_id'] = $node->template_id;

            // Process template parameters with interpolation
            if ($node->template_parameters) {
                $processedData['template_parameters'] = $this->processTemplateParameters(
                    $node->template_parameters
                );
            }
        }

        return $processedData;
    }

    /**
     * Process template parameters with variable interpolation
     */
    private function processTemplateParameters(array $parameters): array
    {
        $processed = [];

        foreach ($parameters as $key => $value) {
            if (is_string($value)) {
                $processed[$key] = $this->interpolate($value);
            } elseif (is_array($value)) {
                $processed[$key] = $this->processTemplateParameters($value);
            } else {
                $processed[$key] = $value;
            }
        }

        return $processed;
    }

    /**
     * Validate interactive message constraints
     * Returns array of validation errors, empty if valid
     */
    public function validateInteractiveMessage(BotNode $node): array
    {
        $errors = [];

        // Validate question_button specific constraints
        if ($node->type->value === 'question_button') {
            // Body is required
            if (empty($node->content)) {
                $errors[] = 'Body content is required for interactive button messages';
            } elseif (strlen($node->content) > 1024) {
                $errors[] = 'Body content exceeds 1024 character limit';
            }

            // Validate buttons
            if (empty($node->options)) {
                $errors[] = 'At least one button is required';
            } elseif (count($node->options) > 3) {
                $errors[] = 'Maximum 3 buttons allowed';
            } else {
                foreach ($node->options as $index => $option) {
                    if (empty($option['id'])) {
                        $errors[] = "Button at index {$index} missing ID";
                    }
                    if (empty($option['title'])) {
                        $errors[] = "Button at index {$index} missing title";
                    } elseif (strlen($option['title']) > 20) {
                        $errors[] = "Button at index {$index} title exceeds 20 character limit";
                    }
                }
            }

            // Validate footer
            if ($node->footer_text && strlen($node->footer_text) > 60) {
                $errors[] = 'Footer text exceeds 60 character limit';
            }

            // Validate header
            if ($node->header_type) {
                if ($node->header_type === 'text' && empty($node->header_text)) {
                    $errors[] = 'Header text is required when header type is text';
                } elseif (in_array($node->header_type, ['image', 'video', 'document']) && empty($node->header_media_url)) {
                    $errors[] = 'Header media URL is required for media headers';
                }
            }
        }

        return $errors;
    }

    /**
     * Create a new instance with specific contact and variables
     * Useful for one-off interpolations
     */
    public function withContext(?Contact $contact = null, array $sessionVariables = []): self
    {
        return new self($contact, $sessionVariables);
    }
}
