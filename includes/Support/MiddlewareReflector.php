<?php

namespace Recca0120\ReverseProxy\Support;

use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;

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
     * @param string|object $class Class name or object instance
     * @return array{label: string, description: string, fields: array}
     */
    public function reflect($class): array
    {
        $reflectionClass = new ReflectionClass($class);
        $constructor = $reflectionClass->getConstructor();

        return [
            'label' => $this->generateLabel($reflectionClass->getShortName()),
            'description' => $this->annotationParser->parseClassDescription($reflectionClass),
            'fields' => $constructor !== null ? $this->buildFields($constructor) : [],
        ];
    }

    /**
     * Reflect a callable (closure or invokable).
     * Returns null since closures cannot provide meaningful UI fields.
     */
    public function reflectCallable(callable $callable): ?array
    {
        return null;
    }

    /**
     * Build field definitions from constructor parameters.
     *
     * @return array<array>
     */
    private function buildFields(ReflectionMethod $constructor): array
    {
        $paramAnnotations = $this->annotationParser->parseConstructorParams($constructor);
        $fields = [];

        foreach ($constructor->getParameters() as $param) {
            $name = $param->getName();
            $annotation = $paramAnnotations[$name] ?? null;

            // Skip non-UI types (callable, object, interfaces)
            if ($this->shouldSkipParameter($param)) {
                continue;
            }

            // Skip parameters marked with (skip)
            if ($annotation !== null && !empty($annotation['skip'])) {
                continue;
            }

            $fields[] = $this->buildField($param, $paramAnnotations);
        }

        return $fields;
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
     * @param array<string, array{type: string, label: string, options: string|null}> $paramAnnotations
     */
    private function buildField(ReflectionParameter $param, array $paramAnnotations): array
    {
        $name = $param->getName();
        $phpType = $this->getParameterTypeName($param);
        $annotation = $paramAnnotations[$name] ?? null;
        $isVariadic = $param->isVariadic();

        $docType = $annotation['type'] ?? null;
        $options = $annotation['options'] ?? null;
        $uiType = $this->resolveUiType($phpType, $docType, $options, $isVariadic);

        return array_merge(
            [
                'name' => $name,
                'type' => $uiType,
                'label' => !empty($annotation['label']) ? $annotation['label'] : $this->generateLabel($name),
            ],
            $this->getFieldOptions($options, $uiType),
            $this->getRepeaterInputType($uiType, $docType, $isVariadic, $phpType),
            $this->getKeyValueLabels($uiType, $annotation['labels'] ?? null),
            $this->getFieldDefault($param, $annotation, $isVariadic, $uiType)
        );
    }

    /**
     * Resolve UI field type from PHP type, PHPDoc type, and options.
     */
    private function resolveUiType(string $phpType, ?string $docType, ?string $options, bool $isVariadic): string
    {
        $uiType = $this->resolveBaseUiType($phpType, $docType, $options);

        if ($isVariadic && Arr::contains(['text', 'number', 'checkbox', 'textarea'], $uiType)) {
            return 'repeater';
        }

        return $uiType;
    }

    /**
     * Get options for select/checkboxes fields.
     */
    private function getFieldOptions(?string $options, string $uiType): array
    {
        if ($options !== null && Arr::contains(['select', 'checkboxes'], $uiType)) {
            return ['options' => $options];
        }

        return [];
    }

    /**
     * Get inputType for repeater fields.
     */
    private function getRepeaterInputType(string $uiType, ?string $docType, bool $isVariadic, string $phpType): array
    {
        if ($uiType !== 'repeater') {
            return [];
        }

        $inputType = null;
        if ($docType !== null) {
            $inputType = $this->extractArrayElementType($docType);
        } elseif ($isVariadic && $phpType === 'int') {
            $inputType = 'number';
        }

        return $inputType !== null ? ['inputType' => $inputType] : [];
    }

    /**
     * Get keyLabel/valueLabel for keyvalue fields.
     */
    private function getKeyValueLabels(string $uiType, ?string $labels): array
    {
        if ($uiType !== 'keyvalue' || $labels === null) {
            return [];
        }

        $labelParts = explode('|', $labels);
        if (count($labelParts) >= 2) {
            return [
                'keyLabel' => trim($labelParts[0]),
                'valueLabel' => trim($labelParts[1]),
            ];
        }

        return [];
    }

    /**
     * Get default value or required flag.
     */
    private function getFieldDefault(ReflectionParameter $param, ?array $annotation, bool $isVariadic, string $uiType): array
    {
        if ($isVariadic) {
            return isset($annotation['default']) ? ['default' => $annotation['default']] : [];
        }

        if ($param->isDefaultValueAvailable()) {
            return ['default' => $this->formatDefault($param->getDefaultValue(), $uiType)];
        }

        if (isset($annotation['default'])) {
            return ['default' => $annotation['default']];
        }

        return ['required' => true];
    }

    /**
     * Extract UI input type from PHPDoc array element type (e.g., int[] → number).
     */
    private function extractArrayElementType(string $docType): ?string
    {
        // Handle int[] or int|int[]
        if (preg_match('/\bint\b/', $docType)) {
            return 'number';
        }

        return null;
    }

    /**
     * Resolve base UI field type based on PHP type, PHPDoc type, and options.
     */
    private function resolveBaseUiType(string $phpType, ?string $docType, ?string $options): string
    {
        // Check for keyvalue type hint: array<string,string>
        if ($docType !== null && preg_match('/^array\s*<\s*string\s*,\s*string\s*>$/', $docType)) {
            return 'keyvalue';
        }

        // Check for typed array from PHPDoc (string[], int[])
        $hasTypedArray = $docType !== null && Str::endsWith($docType, '[]');

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
     * Get type name from parameter's type hint.
     */
    private function getParameterTypeName(ReflectionParameter $param): string
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
}
