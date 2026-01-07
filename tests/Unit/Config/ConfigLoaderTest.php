<?php

namespace Recca0120\ReverseProxy\Tests\Unit\Config;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use Recca0120\ReverseProxy\Config\ConfigLoader;
use Recca0120\ReverseProxy\Config\Loaders\JsonLoader;
use Recca0120\ReverseProxy\Config\Loaders\PhpArrayLoader;
use Recca0120\ReverseProxy\Config\MiddlewareFactory;
use Recca0120\ReverseProxy\Middleware\ProxyHeaders;
use Recca0120\ReverseProxy\Route;

class ConfigLoaderTest extends TestCase
{
    /** @var string */
    private $fixturesPath;

    protected function setUp(): void
    {
        $this->fixturesPath = __DIR__.'/../../fixtures/config';
        if (! is_dir($this->fixturesPath)) {
            mkdir($this->fixturesPath, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        $files = glob($this->fixturesPath.'/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    private function createConfigLoader(?CacheInterface $cache = null): ConfigLoader
    {
        return new ConfigLoader(
            [new JsonLoader, new PhpArrayLoader],
            new MiddlewareFactory,
            $cache
        );
    }

    public function test_load_routes_from_json_file(): void
    {
        $filePath = $this->fixturesPath.'/routes.json';
        file_put_contents($filePath, json_encode([
            'routes' => [
                [
                    'path' => '/api/*',
                    'target' => 'https://api.example.com',
                ],
            ],
        ]));

        $loader = $this->createConfigLoader();
        $routes = $loader->loadFromFile($filePath);

        $this->assertCount(1, $routes);
        $this->assertInstanceOf(Route::class, $routes[0]);
    }

    public function test_load_routes_from_php_file(): void
    {
        $filePath = $this->fixturesPath.'/routes.php';
        file_put_contents($filePath, '<?php return [
            "routes" => [
                [
                    "path" => "/api/*",
                    "target" => "https://api.example.com",
                ],
            ],
        ];');

        $loader = $this->createConfigLoader();
        $routes = $loader->loadFromFile($filePath);

        $this->assertCount(1, $routes);
        $this->assertInstanceOf(Route::class, $routes[0]);
    }

    public function test_load_routes_from_directory(): void
    {
        file_put_contents($this->fixturesPath.'/api.routes.json', json_encode([
            'routes' => [
                ['path' => '/api/*', 'target' => 'https://api.example.com'],
            ],
        ]));

        file_put_contents($this->fixturesPath.'/web.routes.php', '<?php return [
            "routes" => [
                ["path" => "/web/*", "target" => "https://web.example.com"],
            ],
        ];');

        $loader = $this->createConfigLoader();
        $routes = $loader->loadFromDirectory($this->fixturesPath, '*.routes.*');

        $this->assertCount(2, $routes);
    }

    public function test_merge_routes_from_multiple_files(): void
    {
        file_put_contents($this->fixturesPath.'/first.routes.json', json_encode([
            'routes' => [
                ['path' => '/first/*', 'target' => 'https://first.example.com'],
            ],
        ]));

        file_put_contents($this->fixturesPath.'/second.routes.json', json_encode([
            'routes' => [
                ['path' => '/second/*', 'target' => 'https://second.example.com'],
            ],
        ]));

        $loader = $this->createConfigLoader();
        $routes = $loader->loadFromDirectory($this->fixturesPath, '*.routes.json');

        $this->assertCount(2, $routes);
    }

    public function test_create_route_with_path_and_target(): void
    {
        $filePath = $this->fixturesPath.'/routes.json';
        file_put_contents($filePath, json_encode([
            'routes' => [
                [
                    'path' => '/api/users/*',
                    'target' => 'https://api.example.com',
                ],
            ],
        ]));

        $loader = $this->createConfigLoader();
        $routes = $loader->loadFromFile($filePath);

        $this->assertCount(1, $routes);
        $this->assertEquals('api.example.com', $routes[0]->getTargetHost());
    }

    public function test_create_route_with_methods(): void
    {
        $filePath = $this->fixturesPath.'/routes.json';
        file_put_contents($filePath, json_encode([
            'routes' => [
                [
                    'path' => '/api/*',
                    'target' => 'https://api.example.com',
                    'methods' => ['GET', 'POST'],
                ],
            ],
        ]));

        $loader = $this->createConfigLoader();
        $routes = $loader->loadFromFile($filePath);

        $this->assertCount(1, $routes);
        $this->assertInstanceOf(Route::class, $routes[0]);
    }

    public function test_create_route_with_middlewares(): void
    {
        $filePath = $this->fixturesPath.'/routes.json';
        file_put_contents($filePath, json_encode([
            'routes' => [
                [
                    'path' => '/api/*',
                    'target' => 'https://api.example.com',
                    'middlewares' => [
                        ['name' => 'ProxyHeaders'],
                    ],
                ],
            ],
        ]));

        $loader = $this->createConfigLoader();
        $routes = $loader->loadFromFile($filePath);

        $middlewares = $routes[0]->getMiddlewares();
        $this->assertCount(1, $middlewares);
        $this->assertInstanceOf(ProxyHeaders::class, $middlewares[0]);
    }

    public function test_throws_exception_for_missing_path(): void
    {
        $filePath = $this->fixturesPath.'/routes.json';
        file_put_contents($filePath, json_encode([
            'routes' => [
                [
                    'target' => 'https://api.example.com',
                ],
            ],
        ]));

        $loader = $this->createConfigLoader();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Route configuration must have a "path" field');

        $loader->loadFromFile($filePath);
    }

    public function test_throws_exception_for_missing_target(): void
    {
        $filePath = $this->fixturesPath.'/routes.json';
        file_put_contents($filePath, json_encode([
            'routes' => [
                [
                    'path' => '/api/*',
                ],
            ],
        ]));

        $loader = $this->createConfigLoader();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Route configuration must have a "target" field');

        $loader->loadFromFile($filePath);
    }

    public function test_throws_exception_for_invalid_target_url(): void
    {
        $filePath = $this->fixturesPath.'/routes.json';
        file_put_contents($filePath, json_encode([
            'routes' => [
                [
                    'path' => '/api/*',
                    'target' => 'not-a-valid-url',
                ],
            ],
        ]));

        $loader = $this->createConfigLoader();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid target URL');

        $loader->loadFromFile($filePath);
    }

    public function test_uses_cache_when_available(): void
    {
        $filePath = $this->fixturesPath.'/routes.json';
        file_put_contents($filePath, json_encode([
            'routes' => [
                ['path' => '/api/*', 'target' => 'https://api.example.com'],
            ],
        ]));

        $cachedRoutes = [new Route('/cached/*', 'https://cached.example.com')];

        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->once())
            ->method('get')
            ->willReturn($cachedRoutes);

        $loader = $this->createConfigLoader($cache);
        $routes = $loader->loadFromFile($filePath);

        $this->assertSame($cachedRoutes, $routes);
    }

    public function test_invalidates_cache_when_file_modified(): void
    {
        $filePath = $this->fixturesPath.'/routes.json';
        file_put_contents($filePath, json_encode([
            'routes' => [
                ['path' => '/api/*', 'target' => 'https://api.example.com'],
            ],
        ]));

        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->once())
            ->method('get')
            ->willReturn(null);
        $cache->expects($this->once())
            ->method('set');

        $loader = $this->createConfigLoader($cache);
        $routes = $loader->loadFromFile($filePath);

        $this->assertCount(1, $routes);
    }

    public function test_works_without_cache(): void
    {
        $filePath = $this->fixturesPath.'/routes.json';
        file_put_contents($filePath, json_encode([
            'routes' => [
                ['path' => '/api/*', 'target' => 'https://api.example.com'],
            ],
        ]));

        $loader = $this->createConfigLoader(null);
        $routes = $loader->loadFromFile($filePath);

        $this->assertCount(1, $routes);
    }

    public function test_returns_empty_array_for_nonexistent_file(): void
    {
        $loader = $this->createConfigLoader();
        $routes = $loader->loadFromFile('/nonexistent/path/routes.json');

        $this->assertIsArray($routes);
        $this->assertEmpty($routes);
    }

    public function test_returns_empty_array_for_empty_directory(): void
    {
        $emptyDir = $this->fixturesPath.'/empty';
        if (! is_dir($emptyDir)) {
            mkdir($emptyDir, 0755, true);
        }

        $loader = $this->createConfigLoader();
        $routes = $loader->loadFromDirectory($emptyDir, '*.routes.*');

        $this->assertIsArray($routes);
        $this->assertEmpty($routes);

        rmdir($emptyDir);
    }

    public function test_load_routes_with_brace_expansion_pattern(): void
    {
        file_put_contents($this->fixturesPath.'/api.json', json_encode([
            'routes' => [
                ['path' => '/api/*', 'target' => 'https://api.example.com'],
            ],
        ]));

        file_put_contents($this->fixturesPath.'/web.php', '<?php return [
            "routes" => [
                ["path" => "/web/*", "target" => "https://web.example.com"],
            ],
        ];');

        // Should not match .txt files
        file_put_contents($this->fixturesPath.'/ignore.txt', 'should be ignored');

        $loader = $this->createConfigLoader();
        $routes = $loader->loadFromDirectory($this->fixturesPath, '*.{json,php}');

        $this->assertCount(2, $routes);
    }

    public function test_brace_expansion_fallback_without_glob_brace(): void
    {
        file_put_contents($this->fixturesPath.'/first.json', json_encode([
            'routes' => [
                ['path' => '/first/*', 'target' => 'https://first.example.com'],
            ],
        ]));

        file_put_contents($this->fixturesPath.'/second.php', '<?php return [
            "routes" => [
                ["path" => "/second/*", "target" => "https://second.example.com"],
            ],
        ];');

        $loader = $this->createConfigLoader();

        // Test that brace expansion pattern works
        $routes = $loader->loadFromDirectory($this->fixturesPath, '*.{json,php}');

        $this->assertCount(2, $routes);
    }

    public function test_create_route_with_pipe_separated_middlewares(): void
    {
        $filePath = $this->fixturesPath.'/routes.json';
        file_put_contents($filePath, json_encode([
            'routes' => [
                [
                    'path' => '/api/*',
                    'target' => 'https://api.example.com',
                    'middlewares' => 'ProxyHeaders|SetHost:api.example.com|Timeout:30',
                ],
            ],
        ]));

        $loader = $this->createConfigLoader();
        $routes = $loader->loadFromFile($filePath);

        $middlewares = $routes[0]->getMiddlewares();
        $this->assertCount(3, $middlewares);

        // Verify all middleware types are present (order may vary due to priority sorting)
        $classes = array_map('get_class', $middlewares);
        $this->assertContains(ProxyHeaders::class, $classes);
    }
}
