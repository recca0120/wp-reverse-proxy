<?php

namespace Recca0120\ReverseProxy\WordPress\Admin;

use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;
use Recca0120\ReverseProxy\Support\Arr;
use Recca0120\ReverseProxy\Support\Str;

class MiddlewareReflector
{
    /** @var AnnotationParser */
    private $annotationParser;

    /** @var array<string, string> */
    private static $typeMap = [
        'int' => 'number',
        'integer' => 'number',
        'float' => 'number',
        'double' => 'number',
        'string' => 'text',
        'bool' => 'checkbox',
        'boolean' => 'checkbox',
        'array' => 'json',
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

    public function __construct(?AnnotationParser $annotationParser = null)
    {
        $this->annotationParser = $annotationParser ?? new AnnotationParser();
    }

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

        return $this->annotationParser->parseDescription($docComment);
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

        if (Arr::contains($skipTypes, $typeName)) {
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

        $paramType = $info['type'] ?? null;
        $options = $info['options'] ?? null;
        $uiType = $this->resolveUIType($phpType, $paramType, $options, $isVariadic);

        $field = [
            'name' => $name,
            'type' => $uiType,
            'label' => $info['label'] ?? $this->generateLabel($name),
        ];

        $this->addFieldOptions($field, $options, $uiType);
        $this->addRepeaterInputType($field, $uiType, $paramType, $isVariadic, $phpType);
        $this->addKeyValueLabels($field, $uiType, $info['labels'] ?? null);
        $this->addFieldDefault($field, $param, $info, $isVariadic, $uiType);

        return $field;
    }

    /**
     * Resolve UI type with variadic override.
     */
    private function resolveUIType(string $phpType, ?string $paramType, ?string $options, bool $isVariadic): string
    {
        $uiType = $this->determineUIType($phpType, $paramType, $options);

        if ($isVariadic && Arr::contains(['text', 'number', 'checkbox', 'textarea'], $uiType)) {
            return 'repeater';
        }

        return $uiType;
    }

    /**
     * Add options for select/checkboxes fields.
     */
    private function addFieldOptions(array &$field, ?string $options, string $uiType): void
    {
        if ($options !== null && Arr::contains(['select', 'checkboxes'], $uiType)) {
            $field['options'] = $options;
        }
    }

    /**
     * Add inputType for repeater fields.
     */
    private function addRepeaterInputType(array &$field, string $uiType, ?string $paramType, bool $isVariadic, string $phpType): void
    {
        if ($uiType !== 'repeater') {
            return;
        }

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

    /**
     * Add keyLabel/valueLabel for keyvalue fields.
     */
    private function addKeyValueLabels(array &$field, string $uiType, ?string $labels): void
    {
        if ($uiType !== 'keyvalue' || $labels === null) {
            return;
        }

        $labelParts = explode('|', $labels);
        if (count($labelParts) >= 2) {
            $field['keyLabel'] = trim($labelParts[0]);
            $field['valueLabel'] = trim($labelParts[1]);
        }
    }

    /**
     * Add default value or required flag.
     */
    private function addFieldDefault(array &$field, ReflectionParameter $param, ?array $info, bool $isVariadic, string $uiType): void
    {
        if ($isVariadic) {
            if (isset($info['default'])) {
                $field['default'] = $info['default'];
            }
            return;
        }

        if ($param->isDefaultValueAvailable()) {
            $field['default'] = $this->formatDefault($param->getDefaultValue(), $uiType);
        } elseif (isset($info['default'])) {
            $field['default'] = $info['default'];
        } else {
            $field['required'] = true;
        }
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

        // Plain array without PHPDoc type hint = json (allows any structure)
        if ($phpType === 'array') {
            return 'json';
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
        $name = Str::removeSuffix($name, 'Middleware');

        return Str::headline($name, self::$acronyms);
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

                $parsed = $this->annotationParser->parse($description);
                $parsed['type'] = $type;
                $parsed['label'] = $parsed['label'] ?: $this->generateLabel($name);

                $params[$name] = $parsed;
            }
        }

        return $params;
    }
}
