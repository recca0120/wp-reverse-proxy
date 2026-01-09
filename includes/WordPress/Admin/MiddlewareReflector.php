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
     * Extract label from @UILabel annotation or generate from class name.
     */
    private function extractLabel(ReflectionClass $class): string
    {
        $docComment = $class->getDocComment();

        if ($docComment !== false) {
            // Try to find @UILabel annotation
            if (preg_match('/@UILabel\s*\(\s*"([^"]+)"\s*\)/', $docComment, $match)) {
                return $match[1];
            }
        }

        return $this->generateLabel($class->getShortName());
    }

    /**
     * Extract description from @UIDescription annotation or class PHPDoc.
     */
    private function extractDescription(ReflectionClass $class): string
    {
        $docComment = $class->getDocComment();

        if ($docComment === false) {
            return '';
        }

        // Try to find @UIDescription annotation first
        if (preg_match('/@UIDescription\s*\(\s*"([^"]+)"\s*\)/', $docComment, $match)) {
            return $match[1];
        }

        return $this->parseDescription($docComment);
    }

    /**
     * Extract fields from @UIField annotations or constructor parameters.
     *
     * @return array<array>
     */
    private function extractFields(ReflectionClass $class): array
    {
        $constructor = $class->getConstructor();

        if ($constructor === null) {
            return [];
        }

        // Check for @UINoFields annotation - explicitly no fields
        if ($this->hasNoFieldsAnnotation($class, $constructor)) {
            return [];
        }

        // Try to parse @UIField annotations first
        $uiFields = $this->parseUIFieldAnnotations($constructor);

        if (!empty($uiFields)) {
            return $uiFields;
        }

        // Fallback to auto-generating from parameters
        return $this->extractFieldsFromParameters($constructor);
    }

    /**
     * Check if class or constructor has @UINoFields annotation.
     */
    private function hasNoFieldsAnnotation(ReflectionClass $class, ReflectionMethod $constructor): bool
    {
        $classDoc = $class->getDocComment();
        $constructorDoc = $constructor->getDocComment();

        return ($classDoc !== false && strpos($classDoc, '@UINoFields') !== false)
            || ($constructorDoc !== false && strpos($constructorDoc, '@UINoFields') !== false);
    }

    /**
     * Parse @UIField annotations from constructor PHPDoc.
     *
     * Format: @UIField(name="fieldName", type="text", label="Field Label", default="value", required=true)
     *
     * @return array<array>
     */
    private function parseUIFieldAnnotations(ReflectionMethod $method): array
    {
        $docComment = $method->getDocComment();

        if ($docComment === false) {
            return [];
        }

        $fields = [];

        // Match @UIField(...) annotations - handle nested parentheses in quoted strings
        if (preg_match_all('/@UIField\s*\((.+?)\)\s*(?:\*|$)/s', $docComment, $matches)) {
            foreach ($matches[1] as $annotationContent) {
                $field = $this->parseAnnotationAttributes($annotationContent);
                if (!empty($field['name'])) {
                    $fields[] = $field;
                }
            }
        }

        return $fields;
    }

    /**
     * Parse annotation attributes from string like: name="value", type="text"
     */
    private function parseAnnotationAttributes(string $content): array
    {
        $field = [];

        // Match key="value" or key=value patterns
        if (preg_match_all('/(\w+)\s*=\s*(?:"([^"]*)"|(\d+(?:\.\d+)?)|(\w+))/', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $key = $match[1];
                // Priority: quoted string, number, unquoted value
                $value = $match[2] !== '' ? $match[2] : ($match[3] !== '' ? $match[3] : $match[4]);

                // Convert numeric strings to numbers
                if (is_numeric($value)) {
                    $value = strpos($value, '.') !== false ? (float) $value : (int) $value;
                }

                // Convert boolean strings
                if ($value === 'true') {
                    $value = true;
                } elseif ($value === 'false') {
                    $value = false;
                }

                $field[$key] = $value;
            }
        }

        return $field;
    }

    /**
     * Extract fields from constructor parameters (fallback method).
     *
     * @return array<array>
     */
    private function extractFieldsFromParameters(ReflectionMethod $constructor): array
    {
        $paramDescriptions = $this->parseParamDescriptions($constructor);
        $fields = [];

        foreach ($constructor->getParameters() as $param) {
            $fields[] = $this->buildField($param, $paramDescriptions);
        }

        return $fields;
    }

    /**
     * Build field definition from a parameter.
     */
    private function buildField(ReflectionParameter $param, array $descriptions): array
    {
        $name = $param->getName();
        $type = $this->resolveType($param);

        $field = [
            'name' => $name,
            'type' => $this->mapType($type),
            'label' => $this->generateLabel($name),
        ];

        // Add description if available
        if (isset($descriptions[$name])) {
            $field['description'] = $descriptions[$name];
        }

        // Add default value if available
        if ($param->isDefaultValueAvailable()) {
            $default = $param->getDefaultValue();
            if ($type === 'array' && is_array($default)) {
                $field['default'] = implode("\n", $default);
            } else {
                $field['default'] = $default;
            }
        } else {
            $field['required'] = true;
        }

        return $field;
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
     */
    private function generateLabel(string $name): string
    {
        // Split camelCase into words
        $words = preg_replace('/([a-z])([A-Z])/', '$1 $2', $name);

        // Capitalize first letter of each word
        return ucwords($words);
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
     * Parse @param descriptions from method PHPDoc.
     *
     * @return array<string, string> Map of parameter name => description
     */
    private function parseParamDescriptions(ReflectionMethod $method): array
    {
        $docComment = $method->getDocComment();

        if ($docComment === false) {
            return [];
        }

        $descriptions = [];

        // Match @param type $name description
        if (preg_match_all('/@param\s+\S+\s+\$(\w+)\s+(.+)$/m', $docComment, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $descriptions[$match[1]] = trim($match[2]);
            }
        }

        return $descriptions;
    }
}
