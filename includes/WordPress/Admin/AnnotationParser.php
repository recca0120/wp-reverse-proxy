<?php

namespace Recca0120\ReverseProxy\WordPress\Admin;

/**
 * Parse PHPDoc @param description annotations.
 *
 * Supported formats:
 * - (skip) - Hide field from UI
 * - (default: value) - Set default value
 * - (options: a|b|c) - Set options for select/checkboxes
 * - (labels: key|value) - Set labels for keyvalue type (supports \( \) escaping)
 */
class AnnotationParser
{
    /**
     * Parse description and extract annotations.
     *
     * @return array{label: string, options: string|null, default: string|null, labels: string|null, skip: bool}
     */
    public function parse(string $description): array
    {
        $label = $description;
        $options = null;
        $default = null;
        $labels = null;
        $skip = false;

        // Extract (skip)
        if (preg_match('/\(skip\)/i', $description)) {
            $skip = true;
            $label = trim(preg_replace('/\(skip\)/i', '', $label));
        }

        // Extract (default: value)
        if (preg_match('/\(default:\s*([^)]+)\)/', $description, $match)) {
            $default = trim($match[1]);
            $label = trim(preg_replace('/\(default:\s*[^)]+\)/', '', $label));
        }

        // Extract (options: a|b|c)
        if (preg_match('/\(options:\s*([^)]+)\)/', $description, $match)) {
            $options = trim($match[1]);
            $label = trim(preg_replace('/\(options:\s*[^)]+\)/', '', $label));
        }

        // Extract (labels: key|value) - supports \( \) escaping
        if (preg_match('/\(labels:\s*((?:[^)\\\\]|\\\\.)+)\)/', $description, $match)) {
            $labels = trim($match[1]);
            $labels = str_replace(['\\(', '\\)'], ['(', ')'], $labels);
            $label = trim(preg_replace('/\(labels:\s*(?:[^)\\\\]|\\\\.)+\)/', '', $label));
        }

        return [
            'label' => $label,
            'options' => $options,
            'default' => $default,
            'labels' => $labels,
            'skip' => $skip,
        ];
    }
}
