<?php

namespace Recca0120\ReverseProxy\Support;

class Arr
{
    /**
     * Map each item and flatten the results into a single array.
     *
     * @template TKey
     * @template TValue
     * @template TResult
     *
     * @param array<TKey, TValue> $items
     * @param callable(TValue, TKey): array<TResult> $callback
     * @return array<TResult>
     */
    public static function flatMap(array $items, callable $callback): array
    {
        $result = [];

        foreach ($items as $key => $value) {
            foreach ($callback($value, $key) as $item) {
                $result[] = $item;
            }
        }

        return $result;
    }

    /**
     * Flatten a multi-dimensional array into a single level.
     *
     * @template TValue
     *
     * @param array<array<TValue>> $arrays
     * @return array<TValue>
     */
    public static function flatten(array $arrays): array
    {
        $result = [];

        foreach ($arrays as $array) {
            foreach ($array as $item) {
                $result[] = $item;
            }
        }

        return $result;
    }

    /**
     * Determine if an array contains a given value (strict comparison).
     *
     * @template TValue
     *
     * @param array<TValue> $array
     * @param TValue $value
     */
    public static function contains(array $array, $value): bool
    {
        return in_array($value, $array, true);
    }

    /**
     * Determine if a key exists in an array.
     *
     * @param array<string|int, mixed> $array
     * @param string|int $key
     */
    public static function has(array $array, $key): bool
    {
        return array_key_exists($key, $array);
    }

    /**
     * Merge two or more arrays.
     *
     * @param array<mixed> ...$arrays
     * @return array<mixed>
     */
    public static function merge(array ...$arrays): array
    {
        $count = count($arrays);

        if ($count === 0) {
            return [];
        }

        if ($count === 1) {
            return $arrays[0];
        }

        // Optimize for common 2-array case (avoids spread operator overhead)
        if ($count === 2) {
            return array_merge($arrays[0], $arrays[1]);
        }

        return array_merge(...$arrays);
    }

    /**
     * Get the first key from an array (PHP 7.2 compatible).
     *
     * @param array<mixed> $array
     * @return string|int|null
     */
    public static function firstKey(array $array)
    {
        if (function_exists('array_key_first')) {
            return array_key_first($array);
        }

        reset($array);

        return key($array);
    }
}
