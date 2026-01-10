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
            'label' => $this->generateLabel($reflectionClass->getShortName()),
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
     * Extract description from class PHPDoc.
     */
    private function extractDescription(ReflectionClass $class): string
    {
        $docComment = $class->getDocComment();

        if ($docComment === false) {
            return '';
        }

        return $this->annotationParser->extractDescription($docComment);
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

        return $this->buildFields($constructor);
    }

    /**
     * Build field definitions from constructor parameters.
     *
     * @return array<array>
     */
    private function buildFields(ReflectionMethod $constructor): array
    {
        $docComment = $constructor->getDocComment() ?: '';
        $paramAnnotations = $this->annotationParser->parseParams($docComment);
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

            // Skip complex config parameters (has @type block)
            if ($this->annotationParser->hasTypeBlock($docComment, $name)) {
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

        $field = [
            'name' => $name,
            'type' => $uiType,
            'label' => !empty($annotation['label']) ? $annotation['label'] : $this->generateLabel($name),
        ];

        $this->addFieldOptions($field, $options, $uiType);
        $this->addRepeaterInputType($field, $uiType, $docType, $isVariadic, $phpType);
        $this->addKeyValueLabels($field, $uiType, $annotation['labels'] ?? null);
        $this->addFieldDefault($field, $param, $annotation, $isVariadic, $uiType);

        return $field;
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
    private function addRepeaterInputType(array &$field, string $uiType, ?string $docType, bool $isVariadic, string $phpType): void
    {
        if ($uiType !== 'repeater') {
            return;
        }

        $inputType = null;
        if ($docType !== null) {
            $inputType = $this->extractArrayElementType($docType);
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
    private function addFieldDefault(array &$field, ReflectionParameter $param, ?array $annotation, bool $isVariadic, string $uiType): void
    {
        if ($isVariadic) {
            if (isset($annotation['default'])) {
                $field['default'] = $annotation['default'];
            }
            return;
        }

        if ($param->isDefaultValueAvailable()) {
            $field['default'] = $this->formatDefault($param->getDefaultValue(), $uiType);
        } elseif (isset($annotation['default'])) {
            $field['default'] = $annotation['default'];
        } else {
            $field['required'] = true;
        }
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
