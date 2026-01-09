<?php

namespace Recca0120\ReverseProxy\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use Recca0120\ReverseProxy\Support\Str;

class StrTest extends TestCase
{
    /**
     * @dataProvider headlineProvider
     */
    public function test_headline(string $input, string $expected, array $acronyms = [])
    {
        $this->assertEquals($expected, Str::headline($input, $acronyms));
    }

    public static function headlineProvider(): array
    {
        return [
            // Basic camelCase
            ['host', 'Host', []],
            ['allowedOrigins', 'Allowed Origins', []],
            ['maxAge', 'Max Age', []],

            // PascalCase
            ['SetHost', 'Set Host', []],
            ['RequestId', 'Request Id', []],

            // With acronyms
            ['RequestId', 'Request ID', ['id' => 'ID']],
            ['IpFilter', 'IP Filter', ['ip' => 'IP']],
            ['Cors', 'CORS', ['cors' => 'CORS']],
            ['apiUrl', 'API URL', ['api' => 'API', 'url' => 'URL']],

            // Multiple words with acronyms
            ['httpRequestId', 'HTTP Request ID', ['http' => 'HTTP', 'id' => 'ID']],

            // Edge cases
            ['', '', []],
            ['A', 'A', []],
            ['ABC', 'Abc', []],  // No acronym, so ucfirst applies
            ['ABC', 'ABC', ['abc' => 'ABC']],  // With acronym
        ];
    }

    /**
     * @dataProvider removeSuffixProvider
     */
    public function test_remove_suffix(string $input, string $suffix, string $expected)
    {
        $this->assertEquals($expected, Str::removeSuffix($input, $suffix));
    }

    public static function removeSuffixProvider(): array
    {
        return [
            ['TimeoutMiddleware', 'Middleware', 'Timeout'],
            ['CorsMiddleware', 'Middleware', 'Cors'],
            ['SetHost', 'Middleware', 'SetHost'],  // No suffix to remove
            ['Middleware', 'Middleware', ''],       // Entire string is suffix
            ['', 'Middleware', ''],                 // Empty string
            ['Test', '', 'Test'],                   // Empty suffix
        ];
    }

    /**
     * @dataProvider classBasenameProvider
     */
    public function test_class_basename(string $input, string $expected)
    {
        $this->assertEquals($expected, Str::classBasename($input));
    }

    public static function classBasenameProvider(): array
    {
        return [
            ['Recca0120\\ReverseProxy\\Middleware\\Timeout', 'Timeout'],
            ['App\\Models\\User', 'User'],
            ['SimpleClass', 'SimpleClass'],
            ['', ''],
        ];
    }
}
