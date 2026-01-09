<?php

namespace Recca0120\ReverseProxy\WordPress\Admin;

use Recca0120\ReverseProxy\Support\Str;

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
     * Extract annotations from @param description text.
     *
     * @return array{label: string, options: string|null, default: string|null, labels: string|null, skip: bool}
     */
    public function extractAnnotations(string $text): array
    {
        $label = $text;
        $options = null;
        $default = null;
        $labels = null;
        $skip = false;

        // Extract (skip)
        if (preg_match('/\(skip\)/i', $text)) {
            $skip = true;
            $label = trim(preg_replace('/\(skip\)/i', '', $label));
        }

        // Extract (default: value)
        if (preg_match('/\(default:\s*([^)]+)\)/', $text, $match)) {
            $default = trim($match[1]);
            $label = trim(preg_replace('/\(default:\s*[^)]+\)/', '', $label));
        }

        // Extract (options: a|b|c)
        if (preg_match('/\(options:\s*([^)]+)\)/', $text, $match)) {
            $options = trim($match[1]);
            $label = trim(preg_replace('/\(options:\s*[^)]+\)/', '', $label));
        }

        // Extract (labels: key|value) - supports \( \) escaping
        if (preg_match('/\(labels:\s*((?:[^)\\\\]|\\\\.)+)\)/', $text, $match)) {
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

    /**
     * Parse description from PHPDoc block.
     *
     * Extracts the description text before any @tags.
     */
    public function parseDocBlock(string $docComment): string
    {
        if ($docComment === '') {
            return '';
        }

        // Remove /** and */
        $doc = preg_replace('/^\/\*\*\s*|\s*\*\/$/', '', $docComment);

        // Split into lines
        $lines = preg_split('/\r?\n/', $doc);

        $description = [];
        foreach ($lines as $line) {
            // Remove leading * and whitespace
            $line = preg_replace('/^\s*\*\s?/', '', $line);

            // Stop at first @tag
            if (Str::startsWith(trim($line), '@')) {
                break;
            }

            $line = trim($line);
            if ($line !== '') {
                $description[] = $line;
            }
        }

        return implode(' ', $description);
    }
}
