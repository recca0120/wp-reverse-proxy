<?php

namespace Recca0120\ReverseProxy\Support;

class Str
{
    /**
     * Determine if a string starts with a given substring.
     */
    public static function startsWith(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }

        return strpos($haystack, $needle) === 0;
    }

    /**
     * Determine if a string ends with a given substring.
     */
    public static function endsWith(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }

        return substr($haystack, -strlen($needle)) === $needle;
    }

    /**
     * Return the remainder of a string after a given value.
     */
    public static function after(string $subject, string $search): string
    {
        if ($search === '') {
            return $subject;
        }

        $pos = strpos($subject, $search);

        if ($pos === false) {
            return $subject;
        }

        return substr($subject, $pos + strlen($search));
    }

    /**
     * Return the remainder of a string after the last occurrence of a given value.
     */
    public static function afterLast(string $subject, string $search): string
    {
        if ($search === '') {
            return $subject;
        }

        $pos = strrpos($subject, $search);

        if ($pos === false) {
            return $subject;
        }

        return substr($subject, $pos + strlen($search));
    }

    /**
     * Determine if a string contains a given substring.
     */
    public static function contains(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }

        return strpos($haystack, $needle) !== false;
    }

    /**
     * Convert camelCase/PascalCase to "Title Case" with acronym support.
     *
     * @param array<string, string> $acronyms Map of lowercase word to replacement (e.g., ['id' => 'ID'])
     */
    public static function headline(string $value, array $acronyms = []): string
    {
        if ($value === '') {
            return '';
        }

        // Split camelCase into words
        $words = preg_replace('/([a-z])([A-Z])/', '$1 $2', $value);

        // Capitalize and replace acronyms
        $parts = explode(' ', $words);
        $parts = array_map(function ($word) use ($acronyms) {
            $lower = strtolower($word);

            return $acronyms[$lower] ?? ucfirst($lower);
        }, $parts);

        return trim(implode(' ', $parts));
    }

    /**
     * Remove a suffix from a string if present.
     */
    public static function removeSuffix(string $value, string $suffix): string
    {
        if ($suffix === '' || $value === '') {
            return $value;
        }

        if (self::endsWith($value, $suffix)) {
            return substr($value, 0, -strlen($suffix));
        }

        return $value;
    }

    /**
     * Get the class basename from a fully qualified class name.
     */
    public static function classBasename(string $class): string
    {
        return self::afterLast($class, '\\');
    }
}
