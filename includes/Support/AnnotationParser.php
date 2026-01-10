<?php

namespace Recca0120\ReverseProxy\Support;

use ReflectionClass;
use ReflectionMethod;

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
     * Parse @param description text and extract annotations.
     *
     * @return array{label: string, options: string|null, default: string|null, labels: string|null, skip: bool}
     */
    public function parseParamDescription(string $text): array
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
     * Parse class description from PHPDoc block.
     *
     * Extracts the description text before any @tags.
     */
    public function parseClassDescription(ReflectionClass $class): string
    {
        $docComment = $class->getDocComment();

        if ($docComment === false || $docComment === '') {
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

    /**
     * Parse @param annotations from constructor PHPDoc.
     *
     * Format: @param type $name Label {options} = default
     *
     * @return array<string, array{type: string, label: string, options: string|null, default: string|null, labels: string|null, skip: bool}>
     */
    public function parseConstructorParams(ReflectionMethod $constructor): array
    {
        $docComment = $constructor->getDocComment();

        if ($docComment === false || $docComment === '') {
            return [];
        }

        $params = [];

        // Match @param type $name description
        // Type can be: string, int, bool, array, string[], array<string, mixed>, etc.
        // Use .+? to handle types with spaces like "array<string, mixed>"
        if (preg_match_all('/@param\s+(.+?)\s+\$(\w+)(?:\s+(.*))?$/m', $docComment, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $type = $match[1];
                $name = $match[2];
                $description = isset($match[3]) ? trim($match[3]) : '';

                $parsed = $this->parseParamDescription($description);
                $parsed['type'] = $type;

                $params[$name] = $parsed;
            }
        }

        return $params;
    }
}
