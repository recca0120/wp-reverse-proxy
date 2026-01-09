<?php

namespace Recca0120\ReverseProxy\WordPress\Admin;

use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;
use Recca0120\ReverseProxy\Support\Str;

class MiddlewareReflector
{
    /** @var array<string, string> */
    private static $typeMap = [
        'int' => 'number',
        'integer' => 'number',
        'float' => 'number',
        'double' => 'number',
        'string' => 'text',
        'bool' => 'checkbox',
        'boolean' => 'checkbox',
        'array' => 'textarea',
    ];

    /** @var array<string, string> */
    private static $acronyms = [
        'id' => 'ID',
        'ip' => 'IP',
        'url' => 'URL',
        'uri' => 'URI',
        'api' => 'API',
        'cors' => 'CORS',
        'http' => 'HTTP',
        'https' => 'HTTPS',
        'html' => 'HTML',
        'css' => 'CSS',
        'js' => 'JS',
        'json' => 'JSON',
        'xml' => 'XML',
        'sql' => 'SQL',
        'ttl' => 'TTL',
        'php' => 'PHP',
        'phpdoc' => 'PHPDoc',
    ];

    /**
     * Reflect a middleware class and extract UI field definitions.
     *
     * @param string $className
     * @return array{label: string, description: string, fields: array}|null
     */
    public function reflect(string $className): ?array
    {
        if (!class_exists($className)) {
            return null;
        }

        $reflectionClass = new ReflectionClass($className);

        return [
            'label' => $this->extractLabel($reflectionClass),
            'description' => $this->extractDescription($reflectionClass),
            'fields' => $this->extractFields($reflectionClass),
        ];
    }

    /**
     * Reflect a callable (closure or invokable).
     * Returns null since closures cannot provide meaningful UI fields.
     *
     * @param callable $callable
     * @return array|null
     */
    public function reflectCallable(callable $callable): ?array
    {
        // Closures and callable arrays cannot provide meaningful UI fields
        return null;
    }

    /**
     * Extract label from class name.
     */
    private function extractLabel(ReflectionClass $class): string
    {
        return $this->generateLabel($class->getShortName());
    }

    /**
     * Extract description from class PHPDoc.
     */
    private function extractDescription(ReflectionClass $class): string
    {
        $docComment = $class->getDocComment();

        if ($docComment === false) {
            return '';
        }

        return $this->parseDescription($docComment);
    }

    /**
     * Extract fields from @param annotations or constructor parameters.
     *
     * @return array<array>
     */
    private function extractFields(ReflectionClass $class): array
    {
        $constructor = $class->getConstructor();

        if ($constructor === null) {
            return [];
        }

        return $this->extractFieldsFromParameters($constructor);
    }

    /**
     * Extract fields from constructor parameters using @param annotations.
     *
     * @return array<array>
     */
    private function extractFieldsFromParameters(ReflectionMethod $constructor): array
    {
        $docComment = $constructor->getDocComment() ?: '';
        $paramInfos = $this->parseParamAnnotations($constructor);
        $fields = [];

        foreach ($constructor->getParameters() as $param) {
            $name = $param->getName();
            $info = $paramInfos[$name] ?? null;

            // Skip non-UI types (callable, object, interfaces)
            if ($this->shouldSkipParameter($param)) {
                continue;
            }

            // Skip parameters marked with (skip)
            if ($info !== null && !empty($info['skip'])) {
                continue;
            }

            // Skip complex config parameters (has @type block)
            if ($this->hasTypeBlock($docComment, $name)) {
                continue;
            }

            $fields[] = $this->buildField($param, $paramInfos);
        }

        return $fields;
    }

    /**
     * Check if parameter has @type block in PHPDoc (complex config object).
     *
     * Example:
     * @param array $options {
     *     @type string $key Description
     * }
     */
    private function hasTypeBlock(string $docComment, string $paramName): bool
    {
        // Match: @param ... $name ... { ... @type (handles types with spaces like "array<string, mixed>")
        $pattern = '/@param\s+.+?\$' . preg_quote($paramName, '/') . '\s+.*?\{[\s\S]*?@type/';

        return (bool) preg_match($pattern, $docComment);
    }

    /**
     * Check if parameter should be skipped (not shown in UI).
     */
    private function shouldSkipParameter(ReflectionParameter $param): bool
    {
        $type = $param->getType();

        if ($type === null) {
            return false;
        }

        $typeName = method_exists($type, 'getName') ? $type->getName() : (string) $type;

        // Skip callable, Closure, and interface types
        $skipTypes = ['callable', 'Closure', 'object'];

        if (in_array($typeName, $skipTypes, true)) {
            return true;
        }

        // Skip interface types (ends with Interface)
        if (Str::endsWith($typeName, 'Interface')) {
            return true;
        }

        return false;
    }

    /**
     * Build field definition from a parameter.
     *
     * @param array<string, array{type: string, label: string, options: string|null}> $paramInfos
     */
    private function buildField(ReflectionParameter $param, array $paramInfos): array
    {
        $name = $param->getName();
        $phpType = $this->resolveType($param);
        $info = $paramInfos[$name] ?? null;
        $isVariadic = $param->isVariadic();

        // Determine UI type based on PHP type, @param type hint, and options
        $paramType = $info['type'] ?? null;
        $options = $info['options'] ?? null;
        $uiType = $this->determineUIType($phpType, $paramType, $options);

        // Variadic parameters are always arrays - override scalar types to repeater
        if ($isVariadic && in_array($uiType, ['text', 'number', 'checkbox', 'textarea'], true)) {
            $uiType = 'repeater';
        }

        $field = [
            'name' => $name,
            'type' => $uiType,
            'label' => $info['label'] ?? $this->generateLabel($name),
        ];

        // Add options for select/checkboxes
        if ($options !== null && in_array($uiType, ['select', 'checkboxes'], true)) {
            $field['options'] = $options;
        }

        // Add inputType for repeater based on array element type (int[] → number) or variadic type
        if ($uiType === 'repeater') {
            $inputType = null;
            if ($paramType !== null) {
                $inputType = $this->extractArrayElementType($paramType);
            } elseif ($isVariadic && $phpType === 'int') {
                $inputType = 'number';
            }
            if ($inputType !== null) {
                $field['inputType'] = $inputType;
            }
        }

        // Add keyLabel/valueLabel for keyvalue type from labels
        $labels = $info['labels'] ?? null;
        if ($uiType === 'keyvalue' && $labels !== null) {
            $labelParts = explode('|', $labels);
            if (count($labelParts) >= 2) {
                $field['keyLabel'] = trim($labelParts[0]);
                $field['valueLabel'] = trim($labelParts[1]);
            }
        }

        // Add default value if available (prefer PHP default, fallback to PHPDoc default)
        // Variadic parameters are never required (can be empty), but can have PHPDoc default
        if ($isVariadic) {
            if (isset($info['default'])) {
                $field['default'] = $info['default'];
            }
            // Variadic is never required
        } elseif ($param->isDefaultValueAvailable()) {
            $default = $param->getDefaultValue();
            $field['default'] = $this->formatDefault($default, $uiType);
        } elseif (isset($info['default'])) {
            $field['default'] = $info['default'];
        } else {
            $field['required'] = true;
        }

        return $field;
    }

    /**
     * Extract element type from array type hint (e.g., int[] → number, string[] → text).
     */
    private function extractArrayElementType(string $paramType): ?string
    {
        // Handle int[] or int|int[]
        if (preg_match('/\bint\b/', $paramType)) {
            return 'number';
        }

        return null;
    }

    /**
     * Determine UI field type based on PHP type, @param type, and options.
     */
    private function determineUIType(string $phpType, ?string $paramType, ?string $options): string
    {
        // Check for keyvalue type hint: array<string,string>
        if ($paramType !== null && preg_match('/^array\s*<\s*string\s*,\s*string\s*>$/', $paramType)) {
            return 'keyvalue';
        }

        // Check for typed array from PHPDoc (string[], int[])
        $hasTypedArray = $paramType !== null && Str::endsWith($paramType, '[]');

        if ($hasTypedArray) {
            // Typed array with options = checkboxes, without options = repeater
            return $options !== null ? 'checkboxes' : 'repeater';
        }

        // Plain array without PHPDoc type hint = textarea (can't distinguish list from key-value)
        if ($phpType === 'array') {
            return 'textarea';
        }

        // String with options = select
        if ($phpType === 'string' && $options !== null) {
            return 'select';
        }

        // Default mapping
        return self::$typeMap[$phpType] ?? 'text';
    }

    /**
     * Format default value based on UI type.
     *
     * @param mixed $default
     * @return mixed
     */
    private function formatDefault($default, string $uiType)
    {
        if (is_array($default)) {
            // For checkboxes/repeater, join with comma
            return implode(',', $default);
        }

        // Keep boolean as-is for checkbox type
        if (is_bool($default)) {
            return $default;
        }

        return $default;
    }

    /**
     * Resolve parameter type from type hint or PHPDoc.
     */
    private function resolveType(ReflectionParameter $param): string
    {
        $type = $param->getType();

        if ($type !== null) {
            // PHP 7.1+ ReflectionNamedType
            if (method_exists($type, 'getName')) {
                return $type->getName();
            }

            // Fallback for PHP 7.0
            return (string) $type;
        }

        return 'string';
    }

    /**
     * Map PHP type to UI field type.
     */
    private function mapType(string $phpType): string
    {
        return self::$typeMap[$phpType] ?? 'text';
    }

    /**
     * Generate human-readable label from name.
     *
     * Examples:
     * - "host" => "Host"
     * - "allowedOrigins" => "Allowed Origins"
     * - "maxAge" => "Max Age"
     * - "Cors" => "CORS"
     * - "RequestId" => "Request ID"
     * - "IpFilter" => "IP Filter"
     * - "NoPHPDocMiddleware" => "No PHPDoc"
     */
    private function generateLabel(string $name): string
    {
        // Remove common suffixes
        $name = preg_replace('/Middleware$/', '', $name);

        // Split camelCase into words
        $words = preg_replace('/([a-z])([A-Z])/', '$1 $2', $name);

        // Capitalize and replace acronyms
        $parts = explode(' ', $words);
        $parts = array_map(function ($word) {
            $lower = strtolower($word);

            return self::$acronyms[$lower] ?? ucfirst($lower);
        }, $parts);

        return trim(implode(' ', $parts));
    }

    /**
     * Parse description from PHPDoc.
     */
    private function parseDescription(string $docComment): string
    {
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
     * Parse @param annotations from method PHPDoc.
     *
     * Format: @param type $name Label {options} = default
     *
     * @return array<string, array{type: string, label: string, options: string|null, default: string|null}>
     */
    private function parseParamAnnotations(ReflectionMethod $method): array
    {
        $docComment = $method->getDocComment();

        if ($docComment === false) {
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

                $params[$name] = $this->parseParamDescription($description, $name, $type);
            }
        }

        return $params;
    }

    /**
     * Parse @param description to extract label, options, default, skip, and labels.
     *
     * Format: "Label (options: a|b) (default: value) (labels: key|value) (skip)"
     */
    private function parseParamDescription(string $description, string $name, string $type): array
    {
        $label = $description;
        $options = null;
        $default = null;
        $skip = false;
        $labels = null;

        // Extract (skip)
        if (preg_match('/\(skip\)/i', $description)) {
            $skip = true;
            $label = trim(preg_replace('/\(skip\)/i', '', $label));
        }

        // Extract (default: value)
        if (preg_match('/\(default:\s*([^)]+)\)/', $description, $defaultMatch)) {
            $default = trim($defaultMatch[1]);
            $label = trim(preg_replace('/\(default:\s*[^)]+\)/', '', $label));
        }

        // Extract (options: a|b|c)
        if (preg_match('/\(options:\s*([^)]+)\)/', $description, $optionsMatch)) {
            $options = trim($optionsMatch[1]);
            $label = trim(preg_replace('/\(options:\s*[^)]+\)/', '', $label));
        }

        // Extract (labels: key|value) for keyvalue type (use \( \) to escape parentheses)
        if (preg_match('/\(labels:\s*((?:[^)\\\\]|\\\\.)+)\)/', $description, $labelsMatch)) {
            $labels = trim($labelsMatch[1]);
            // Unescape \( and \) in labels
            $labels = str_replace(['\\(', '\\)'], ['(', ')'], $labels);
            $label = trim(preg_replace('/\(labels:\s*(?:[^)\\\\]|\\\\.)+\)/', '', $label));
        }

        return [
            'type' => $type,
            'label' => $label ?: $this->generateLabel($name),
            'options' => $options,
            'default' => $default,
            'skip' => $skip,
            'labels' => $labels,
        ];
    }
}
