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
        $this->fixturesPath = __DIR__.'/../../../fixtures/config/json';
    }

    public function test_supports_json_extension(): void
    {
        $loader = new JsonLoader();

        $this->assertTrue($loader->supports('/path/to/routes.json'));
        $this->assertTrue($loader->supports('/path/to/reverse-proxy-routes.json'));
    }

    public function test_does_not_support_php_extension(): void
    {
        $loader = new JsonLoader();

        $this->assertFalse($loader->supports('/path/to/routes.php'));
        $this->assertFalse($loader->supports('/path/to/config.yaml'));
        $this->assertFalse($loader->supports('/path/to/file.txt'));
    }

    public function test_load_valid_json_file(): void
    {
        $loader = new JsonLoader();
        $result = $loader->load($this->fixturesPath.'/valid-routes.json');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('routes', $result);
        $this->assertCount(1, $result['routes']);
        $this->assertEquals('/api/*', $result['routes'][0]['path']);
        $this->assertEquals('https://api.example.com', $result['routes'][0]['target']);
    }

    public function test_load_returns_empty_array_for_invalid_json(): void
    {
        $loader = new JsonLoader();
        $result = $loader->load($this->fixturesPath.'/invalid.json');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_load_returns_empty_array_for_nonexistent_file(): void
    {
        $loader = new JsonLoader();
        $result = $loader->load('/nonexistent/path/routes.json');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
