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
        return array_reduce(array_keys($items), function ($result, $key) use ($items, $callback) {
            return self::merge($result, $callback($items[$key], $key));
        }, []);
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

    /**
     * Normalize variadic arguments - unwrap single array element.
     *
     * Supports both: func('a', 'b') and func(['a', 'b'])
     *
     * @template T
     * @param array<T|array<T>> $values
     * @return array<T>
     */
    public static function wrap(array $values): array
    {
        if (count($values) === 1 && is_array($values[0])) {
            return $values[0];
        }

        return $values;
    }

    /**
     * Get the first item where the given key matches the value.
     *
     * @template TValue
     *
     * @param array<TValue> $array
     * @param string $key
     * @param mixed $value
     * @return TValue|null
     */
    public static function firstWhere(array $array, string $key, $value)
    {
        foreach ($array as $item) {
            if (isset($item[$key]) && $item[$key] === $value) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Search the array for the key where the given field matches the value.
     *
     * @param array<mixed> $array
     * @param string $key
     * @param mixed $value
     * @return int|string|null
     */
    public static function search(array $array, string $key, $value)
    {
        foreach ($array as $k => $item) {
            if (isset($item[$key]) && $item[$key] === $value) {
                return $k;
            }
        }

        return null;
    }
}
