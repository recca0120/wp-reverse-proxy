<?php

namespace Recca0120\ReverseProxy\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use Recca0120\ReverseProxy\Support\Arr;

class ArrTest extends TestCase
{
    public function test_flat_map_with_simple_arrays(): void
    {
        $result = Arr::flatMap([1, 2, 3], function ($n) {
            return [$n, $n * 2];
        });

        $this->assertEquals([1, 2, 2, 4, 3, 6], $result);
    }

    public function test_flat_map_with_empty_array(): void
    {
        $result = Arr::flatMap([], function ($n) {
            return [$n];
        });

        $this->assertEquals([], $result);
    }

    public function test_flat_map_with_callback_returning_empty(): void
    {
        $result = Arr::flatMap([1, 2, 3], function ($n) {
            return [];
        });

        $this->assertEquals([], $result);
    }

    public function test_flat_map_receives_key(): void
    {
        $result = Arr::flatMap(['a' => 1, 'b' => 2], function ($value, $key) {
            return [$key => $value];
        });

        $this->assertEquals([1, 2], array_values($result));
    }

    public function test_flat_map_with_nested_arrays(): void
    {
        $items = [
            ['extensions' => ['json']],
            ['extensions' => ['yaml', 'yml']],
            ['extensions' => ['php']],
        ];

        $result = Arr::flatMap($items, function ($item) {
            return $item['extensions'];
        });

        $this->assertEquals(['json', 'yaml', 'yml', 'php'], $result);
    }

    public function test_flatten_simple_arrays(): void
    {
        $result = Arr::flatten([
            [1, 2],
            [3, 4],
            [5],
        ]);

        $this->assertEquals([1, 2, 3, 4, 5], $result);
    }

    public function test_flatten_empty_array(): void
    {
        $result = Arr::flatten([]);

        $this->assertEquals([], $result);
    }

    public function test_flatten_with_empty_nested_arrays(): void
    {
        $result = Arr::flatten([
            [1, 2],
            [],
            [3],
        ]);

        $this->assertEquals([1, 2, 3], $result);
    }

    public function test_flatten_single_level(): void
    {
        $result = Arr::flatten([
            ['a', 'b'],
        ]);

        $this->assertEquals(['a', 'b'], $result);
    }

    /**
     * @dataProvider wrapProvider
     */
    public function test_wrap(array $input, array $expected): void
    {
        $this->assertEquals($expected, Arr::wrap($input));
    }

    public static function wrapProvider(): array
    {
        return [
            // Single array element - unwrap it
            [[[1, 2, 3]], [1, 2, 3]],
            [[['GET', 'POST']], ['GET', 'POST']],

            // Multiple elements - keep as is
            [['GET', 'POST'], ['GET', 'POST']],
            [[1, 2, 3], [1, 2, 3]],

            // Empty array - keep as is
            [[], []],

            // Single non-array element - keep as is
            [['GET'], ['GET']],
            [[404], [404]],

            // Single nested empty array - unwrap it
            [[[]], []],
        ];
    }
}
