<?php

namespace Recca0120\ReverseProxy\Tests\Unit\Config\Loaders;

use PHPUnit\Framework\TestCase;
use Recca0120\ReverseProxy\Config\Loaders\PhpArrayLoader;

class PhpArrayLoaderTest extends TestCase
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

    public function test_supports_php_extension(): void
    {
        $loader = new PhpArrayLoader;

        $this->assertTrue($loader->supports('/path/to/routes.php'));
        $this->assertTrue($loader->supports('/path/to/reverse-proxy-routes.php'));
    }

    public function test_does_not_support_json_extension(): void
    {
        $loader = new PhpArrayLoader;

        $this->assertFalse($loader->supports('/path/to/routes.json'));
        $this->assertFalse($loader->supports('/path/to/config.yaml'));
        $this->assertFalse($loader->supports('/path/to/file.txt'));
    }

    public function test_load_php_file_returning_array(): void
    {
        $filePath = $this->fixturesPath.'/valid-routes.php';
        file_put_contents($filePath, '<?php return [
            "routes" => [
                [
                    "path" => "/api/*",
                    "target" => "https://api.example.com",
                ],
            ],
        ];');

        $loader = new PhpArrayLoader;
        $result = $loader->load($filePath);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('routes', $result);
        $this->assertCount(1, $result['routes']);
        $this->assertEquals('/api/*', $result['routes'][0]['path']);
        $this->assertEquals('https://api.example.com', $result['routes'][0]['target']);

        unlink($filePath);
    }
}
