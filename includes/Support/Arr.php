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
}
