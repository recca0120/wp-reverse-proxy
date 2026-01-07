<?php

namespace Recca0120\ReverseProxy\Tests\Unit\Config\Loaders;

use PHPUnit\Framework\TestCase;
use Recca0120\ReverseProxy\Config\Loaders\JsonLoader;

class JsonLoaderTest extends TestCase
{
    /** @var string */
    private $fixturesPath;

    protected function setUp(): void
    {
        $this->fixturesPath = __DIR__.'/../../../fixtures/config';
        if (! is_dir($this->fixturesPath)) {
            mkdir($this->fixturesPath, 0755, true);
        }
    }

    public function test_supports_json_extension(): void
    {
        $loader = new JsonLoader;

        $this->assertTrue($loader->supports('/path/to/routes.json'));
        $this->assertTrue($loader->supports('/path/to/reverse-proxy-routes.json'));
    }

    public function test_does_not_support_php_extension(): void
    {
        $loader = new JsonLoader;

        $this->assertFalse($loader->supports('/path/to/routes.php'));
        $this->assertFalse($loader->supports('/path/to/config.yaml'));
        $this->assertFalse($loader->supports('/path/to/file.txt'));
    }

    public function test_load_valid_json_file(): void
    {
        $filePath = $this->fixturesPath.'/valid-routes.json';
        file_put_contents($filePath, json_encode([
            'routes' => [
                [
                    'path' => '/api/*',
                    'target' => 'https://api.example.com',
                ],
            ],
        ]));

        $loader = new JsonLoader;
        $result = $loader->load($filePath);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('routes', $result);
        $this->assertCount(1, $result['routes']);
        $this->assertEquals('/api/*', $result['routes'][0]['path']);
        $this->assertEquals('https://api.example.com', $result['routes'][0]['target']);

        unlink($filePath);
    }

    public function test_load_returns_empty_array_for_invalid_json(): void
    {
        $filePath = $this->fixturesPath.'/invalid.json';
        file_put_contents($filePath, 'not valid json {{{');

        $loader = new JsonLoader;
        $result = $loader->load($filePath);

        $this->assertIsArray($result);
        $this->assertEmpty($result);

        unlink($filePath);
    }

    public function test_load_returns_empty_array_for_nonexistent_file(): void
    {
        $loader = new JsonLoader;
        $result = $loader->load('/nonexistent/path/routes.json');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
