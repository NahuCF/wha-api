<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\Template;

class TemplateComponentBuilderService
{
    /**
     * Build template components for Meta API from template and variables
     */
    public function build(Template $template, array $variables = []): array
    {
        $components = [];

        // Handle header variables if template has header text with variables
        if ($template->header_type === 'text' && ! empty($template->header_text)) {
            $headerParams = $this->extractParameters($template->header_text, $variables);
            if (! empty($headerParams)) {
                $components[] = [
                    'type' => 'header',
                    'parameters' => $headerParams,
                ];
            }
        }

        // Handle body variables
        if (! empty($template->body)) {
            $bodyParams = $this->extractParameters($template->body, $variables);
            if (! empty($bodyParams)) {
                $components[] = [
                    'type' => 'body',
                    'parameters' => $bodyParams,
                ];
            }
        }

        // Handle footer variables if any
        if (! empty($template->footer)) {
            $footerParams = $this->extractParameters($template->footer, $variables);
            if (! empty($footerParams)) {
                $components[] = [
                    'type' => 'footer',
                    'parameters' => $footerParams,
                ];
            }
        }

        // Handle buttons with dynamic URLs
        if (! empty($template->buttons)) {
            $buttonComponents = $this->buildButtonComponents($template->buttons, $variables);
            if (! empty($buttonComponents)) {
                $components = array_merge($components, $buttonComponents);
            }
        }

        return $components;
    }

    /**
     * Build components for a contact using broadcast variable configuration
     */
    public function buildForContact(Template $template, Contact $contact, array $variableConfig = []): array
    {
        $variables = $this->getContactVariables($contact, $variableConfig);

        return $this->build($template, $variables);
    }

    /**
     * Extract parameters from text by finding {{1}}, {{2}} placeholders
     */
    private function extractParameters(string $text, array $variables): array
    {
        $params = [];

        // Find all placeholders like {{1}}, {{2}}, etc.
        preg_match_all('/\{\{(\d+)\}\}/', $text, $matches);

        if (! empty($matches[1])) {
            foreach ($matches[1] as $index) {
                $value = $variables[$index] ?? '';
                $params[] = [
                    'type' => 'text',
                    'text' => (string) $value,
                ];
            }
        }

        return $params;
    }

    /**
     * Build button components with dynamic parameters
     */
    private function buildButtonComponents(array $buttons, array $variables): array
    {
        $components = [];

        foreach ($buttons as $index => $button) {
            if ($button['type'] === 'url' && isset($button['url'])) {
                // Check if URL has variables
                if (strpos($button['url'], '{{') !== false) {
                    preg_match('/\{\{(\d+)\}\}/', $button['url'], $matches);
                    if (! empty($matches[1])) {
                        $urlParam = $variables[$matches[1]] ?? '';
                        $components[] = [
                            'type' => 'button',
                            'sub_type' => 'url',
                            'index' => $index,
                            'parameters' => [
                                [
                                    'type' => 'text',
                                    'text' => $urlParam,
                                ],
                            ],
                        ];
                    }
                }
            } elseif ($button['type'] === 'quick_reply') {
                // Quick reply buttons might have dynamic payloads
                if (isset($button['payload']) && strpos($button['payload'], '{{') !== false) {
                    preg_match('/\{\{(\d+)\}\}/', $button['payload'], $matches);
                    if (! empty($matches[1])) {
                        $payloadParam = $variables[$matches[1]] ?? '';
                        $components[] = [
                            'type' => 'button',
                            'sub_type' => 'quick_reply',
                            'index' => $index,
                            'parameters' => [
                                [
                                    'type' => 'payload',
                                    'payload' => $payloadParam,
                                ],
                            ],
                        ];
                    }
                }
            }
        }

        return $components;
    }

    /**
     * Get variable values for a contact based on broadcast variables configuration
     */
    public function getContactVariables(Contact $contact, array $variableConfig = []): array
    {
        $result = [];

        // Variables structure: [
        //   { "name": "{{1}}", "contact_field_id": "field_id", "value": null },
        //   { "name": "{{2}}", "contact_field_id": null, "value": "static value" }
        // ]
        foreach ($variableConfig as $variable) {
            // Extract the index from the name (e.g., "{{1}}" -> 1)
            preg_match('/\{\{(\d+)\}\}/', $variable['name'] ?? '', $matches);
            $index = $matches[1] ?? null;

            if (! $index) {
                continue;
            }

            // If contact_field_id is set, get value from contact field
            if (! empty($variable['contact_field_id'])) {
                $fieldValue = $this->getContactFieldValue($contact, $variable['contact_field_id']);
                $result[$index] = $fieldValue ?? $variable['value'] ?? '';
            } else {
                // Use static value
                $result[$index] = $variable['value'] ?? '';
            }
        }

        return $result;
    }

    /**
     * Get contact field value by field ID
     */
    private function getContactFieldValue(Contact $contact, string $fieldId): ?string
    {
        $fieldValue = $contact->fieldValues->first(function ($fv) use ($fieldId) {
            return $fv->contact_field_id === $fieldId;
        });

        if (! $fieldValue || ! $fieldValue->value) {
            return null;
        }

        // Handle different field types
        if (is_array($fieldValue->value)) {
            return implode(', ', $fieldValue->value);
        }

        return (string) $fieldValue->value;
    }

    /**
     * Replace variables in template content (for preview/display purposes)
     */
    public function replaceVariablesInContent(string $content, array $variables): string
    {
        foreach ($variables as $index => $value) {
            $placeholder = '{{'.$index.'}}';
            $content = str_replace($placeholder, $value, $content);
        }

        return $content;
    }
}
