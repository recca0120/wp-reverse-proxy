<?php

namespace Recca0120\ReverseProxy\Tests\Unit\Config\Loaders;

use PHPUnit\Framework\TestCase;
use Recca0120\ReverseProxy\Config\Loaders\YamlLoader;

class YamlLoaderTest extends TestCase
{
    /** @var string */
    private $fixturesPath;

    protected function setUp(): void
    {
        $this->fixturesPath = __DIR__.'/../../../fixtures/config/yaml';
    }

    public function test_supports_yaml_extension(): void
    {
        $loader = new YamlLoader;

        $this->assertTrue($loader->supports('/path/to/routes.yaml'));
        $this->assertTrue($loader->supports('/path/to/reverse-proxy-routes.yaml'));
    }

    public function test_supports_yml_extension(): void
    {
        $loader = new YamlLoader;

        $this->assertTrue($loader->supports('/path/to/routes.yml'));
        $this->assertTrue($loader->supports('/path/to/reverse-proxy-routes.yml'));
    }

    public function test_does_not_support_other_extensions(): void
    {
        $loader = new YamlLoader;

        $this->assertFalse($loader->supports('/path/to/routes.php'));
        $this->assertFalse($loader->supports('/path/to/routes.json'));
        $this->assertFalse($loader->supports('/path/to/file.txt'));
    }

    public function test_load_valid_yaml_file(): void
    {
        $loader = new YamlLoader;
        $result = $loader->load($this->fixturesPath.'/valid-routes.yaml');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('routes', $result);
        $this->assertCount(1, $result['routes']);
        $this->assertEquals('/api/*', $result['routes'][0]['path']);
        $this->assertEquals('https://api.example.com', $result['routes'][0]['target']);
    }

    public function test_load_yaml_with_anchors_and_aliases(): void
    {
        $loader = new YamlLoader;
        $result = $loader->load($this->fixturesPath.'/anchors-routes.yaml');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('routes', $result);
        $this->assertCount(2, $result['routes']);

        // First route should have default values
        $this->assertEquals(30, $result['routes'][0]['options']['timeout']);
        $this->assertEquals(3, $result['routes'][0]['options']['retries']);

        // Second route should have overridden timeout
        $this->assertEquals(60, $result['routes'][1]['options']['timeout']);
        $this->assertEquals(3, $result['routes'][1]['options']['retries']);
    }

    public function test_load_returns_empty_array_for_invalid_yaml(): void
    {
        $loader = new YamlLoader;
        $result = $loader->load($this->fixturesPath.'/invalid.yaml');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_load_returns_empty_array_for_nonexistent_file(): void
    {
        $loader = new YamlLoader;
        $result = $loader->load('/nonexistent/path/routes.yaml');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_load_returns_empty_array_for_empty_file(): void
    {
        $loader = new YamlLoader;
        $result = $loader->load($this->fixturesPath.'/empty.yaml');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
