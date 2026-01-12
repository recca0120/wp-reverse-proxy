<?php

namespace Recca0120\ReverseProxy\Tests\Unit\Routing;

use Mockery;
use PHPUnit\Framework\TestCase;
use Recca0120\ReverseProxy\Contracts\RouteLoaderInterface;
use Recca0120\ReverseProxy\Middleware\ProxyHeaders;
use Recca0120\ReverseProxy\Routing\FileLoader;
use Recca0120\ReverseProxy\Routing\Route;
use Recca0120\ReverseProxy\Routing\RouteCollection;
use Recca0120\ReverseProxy\Tests\Stubs\ArrayCache;

class FileLoaderTest extends TestCase
{
    /** @var string */
    private $fixturesPath;

    protected function setUp(): void
    {
        $this->fixturesPath = __DIR__ . '/../../fixtures/config';
        if (!is_dir($this->fixturesPath)) {
            mkdir($this->fixturesPath, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        Mockery::close();

        $files = glob($this->fixturesPath . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    public function test_it_implements_route_loader_interface(): void
    {
        $loader = new FileLoader([]);

        $this->assertInstanceOf(RouteLoaderInterface::class, $loader);
    }

    public function test_load_routes_from_json_file(): void
    {
        $filePath = $this->fixturesPath . '/routes.json';
        file_put_contents($filePath, json_encode([
            'routes' => [
                [
                    'path' => '/api/*',
                    'target' => 'https://api.example.com',
                ],
            ],
        ]));

        $routes = $this->loadRoutes(new FileLoader([$filePath]));

        $this->assertCount(1, $routes);
        $this->assertInstanceOf(Route::class, $routes[0]);
    }

    public function test_load_routes_from_yaml_file(): void
    {
        $filePath = $this->fixturesPath . '/routes.yaml';
        $yaml = "routes:\n  - path: /api/*\n    target: https://api.example.com\n";
        file_put_contents($filePath, $yaml);

        $routes = $this->loadRoutes(new FileLoader([$filePath]));

        $this->assertCount(1, $routes);
        $this->assertInstanceOf(Route::class, $routes[0]);
    }

    public function test_load_routes_from_yaml_file_with_anchors(): void
    {
        $filePath = $this->fixturesPath . '/routes.yaml';
        $yaml = implode("\n", [
            'defaults: &defaults',
            '  middlewares:',
            '    - ProxyHeaders',
            '',
            'routes:',
            '  - path: /api/*',
            '    target: https://api.example.com',
            '    <<: *defaults',
            '  - path: /web/*',
            '    target: https://web.example.com',
            '    <<: *defaults',
        ]);
        file_put_contents($filePath, $yaml);

        $routes = $this->loadRoutes(new FileLoader([$filePath]));

        $this->assertCount(2, $routes);
        $this->assertCount(1, $routes[0]->getMiddlewares());
        $this->assertCount(1, $routes[1]->getMiddlewares());
    }

    public function test_load_routes_from_php_file(): void
    {
        $filePath = $this->fixturesPath . '/routes.php';
        file_put_contents($filePath, '<?php return [
            "routes" => [
                [
                    "path" => "/api/*",
                    "target" => "https://api.example.com",
                ],
            ],
        ];');

        $routes = $this->loadRoutes(new FileLoader([$filePath]));

        $this->assertCount(1, $routes);
        $this->assertInstanceOf(Route::class, $routes[0]);
    }

    public function test_load_routes_from_directory(): void
    {
        file_put_contents($this->fixturesPath . '/api.json', json_encode([
            'routes' => [
                ['path' => '/api/*', 'target' => 'https://api.example.com'],
            ],
        ]));

        file_put_contents($this->fixturesPath . '/web.php', '<?php return [
            "routes" => [
                ["path" => "/web/*", "target" => "https://web.example.com"],
            ],
        ];');

        $routes = $this->loadRoutes(new FileLoader([$this->fixturesPath]));

        $this->assertCount(2, $routes);
    }

    public function test_load_routes_from_multiple_paths(): void
    {
        $filePath1 = $this->fixturesPath . '/first.json';
        $filePath2 = $this->fixturesPath . '/second.json';

        file_put_contents($filePath1, json_encode([
            'routes' => [
                ['path' => '/first/*', 'target' => 'https://first.example.com'],
            ],
        ]));

        file_put_contents($filePath2, json_encode([
            'routes' => [
                ['path' => '/second/*', 'target' => 'https://second.example.com'],
            ],
        ]));

        $routes = $this->loadRoutes(new FileLoader([$filePath1, $filePath2]));

        $this->assertCount(2, $routes);
    }

    public function test_create_route_with_methods(): void
    {
        $filePath = $this->fixturesPath . '/routes.json';
        file_put_contents($filePath, json_encode([
            'routes' => [
                [
                    'path' => '/api/*',
                    'target' => 'https://api.example.com',
                    'methods' => ['GET', 'POST'],
                ],
            ],
        ]));

        $routes = $this->loadRoutes(new FileLoader([$filePath]));

        $this->assertCount(1, $routes);
        $this->assertInstanceOf(Route::class, $routes[0]);
    }

    public function test_create_route_with_middlewares(): void
    {
        $filePath = $this->fixturesPath . '/routes.json';
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

        $routes = $this->loadRoutes(new FileLoader([$filePath]));

        $middlewares = $routes[0]->getMiddlewares();
        $this->assertCount(1, $middlewares);
        $this->assertInstanceOf(ProxyHeaders::class, $middlewares[0]);
    }

    public function test_skips_invalid_routes(): void
    {
        $filePath = $this->fixturesPath . '/routes.json';
        file_put_contents($filePath, json_encode([
            'routes' => [
                [
                    'target' => 'https://api.example.com', // missing path
                ],
                [
                    'path' => '/valid/*',
                    'target' => 'https://valid.example.com',
                ],
            ],
        ]));

        $routes = $this->loadRoutes(new FileLoader([$filePath]));

        $this->assertCount(1, $routes);
        $this->assertEquals('valid.example.com', $routes[0]->getTargetHost());
    }

    public function test_skips_route_with_missing_target(): void
    {
        $filePath = $this->fixturesPath . '/routes.json';
        file_put_contents($filePath, json_encode([
            'routes' => [
                [
                    'path' => '/api/*', // missing target
                ],
                [
                    'path' => '/valid/*',
                    'target' => 'https://valid.example.com',
                ],
            ],
        ]));

        $routes = $this->loadRoutes(new FileLoader([$filePath]));

        $this->assertCount(1, $routes);
        $this->assertEquals('valid.example.com', $routes[0]->getTargetHost());
    }

    public function test_skips_route_with_invalid_target_url(): void
    {
        $filePath = $this->fixturesPath . '/routes.json';
        file_put_contents($filePath, json_encode([
            'routes' => [
                [
                    'path' => '/api/*',
                    'target' => 'not-a-valid-url',
                ],
                [
                    'path' => '/valid/*',
                    'target' => 'https://valid.example.com',
                ],
            ],
        ]));

        $routes = $this->loadRoutes(new FileLoader([$filePath]));

        $this->assertCount(1, $routes);
        $this->assertEquals('valid.example.com', $routes[0]->getTargetHost());
    }

    public function test_uses_cache_when_available(): void
    {
        $filePath = $this->fixturesPath . '/routes.json';
        file_put_contents($filePath, json_encode([
            'routes' => [
                ['path' => '/api/*', 'target' => 'https://api.example.com'],
            ],
        ]));

        $loader = new FileLoader([$filePath]);
        $cacheKey = $loader->getCacheKey();
        $mtime = filemtime($filePath);

        $cachedConfigs = [
            ['path' => '/cached/*', 'target' => 'https://cached.example.com'],
        ];

        $cache = new ArrayCache();
        $cache->set($cacheKey, ['metadata' => $mtime, 'data' => $cachedConfigs]);

        $collection = new RouteCollection([$loader], $cache);
        $collection->load();

        $this->assertCount(1, $collection);
        $this->assertEquals('cached.example.com', $collection[0]->getTargetHost());
    }

    public function test_stores_cache_when_not_cached(): void
    {
        $filePath = $this->fixturesPath . '/routes.json';
        file_put_contents($filePath, json_encode([
            'routes' => [
                ['path' => '/api/*', 'target' => 'https://api.example.com'],
            ],
        ]));

        $loader = new FileLoader([$filePath]);
        $cacheKey = $loader->getCacheKey();

        $cache = new ArrayCache();

        $collection = new RouteCollection([$loader], $cache);
        $collection->load();

        $this->assertCount(1, $collection);

        $cachedData = $cache->get($cacheKey);
        $this->assertNotNull($cachedData);
        $this->assertArrayHasKey('metadata', $cachedData);
        $this->assertArrayHasKey('data', $cachedData);
    }

    public function test_returns_empty_array_for_nonexistent_file(): void
    {
        $routes = $this->loadRoutes(new FileLoader(['/nonexistent/path/routes.json']));

        $this->assertIsArray($routes);
        $this->assertEmpty($routes);
    }

    public function test_returns_empty_array_for_empty_directory(): void
    {
        $emptyDir = $this->fixturesPath . '/empty';
        if (!is_dir($emptyDir)) {
            mkdir($emptyDir, 0755, true);
        }

        $routes = $this->loadRoutes(new FileLoader([$emptyDir]));

        $this->assertIsArray($routes);
        $this->assertEmpty($routes);

        rmdir($emptyDir);
    }

    public function test_load_routes_ignores_unsupported_files(): void
    {
        file_put_contents($this->fixturesPath . '/api.json', json_encode([
            'routes' => [
                ['path' => '/api/*', 'target' => 'https://api.example.com'],
            ],
        ]));

        file_put_contents($this->fixturesPath . '/web.php', '<?php return [
            "routes" => [
                ["path" => "/web/*", "target" => "https://web.example.com"],
            ],
        ];');

        file_put_contents($this->fixturesPath . '/ignore.txt', 'should be ignored');

        $routes = $this->loadRoutes(new FileLoader([$this->fixturesPath]));

        $this->assertCount(2, $routes);
    }

    public function test_create_route_with_pipe_separated_middlewares(): void
    {
        $filePath = $this->fixturesPath . '/routes.json';
        file_put_contents($filePath, json_encode([
            'routes' => [
                [
                    'path' => '/api/*',
                    'target' => 'https://api.example.com',
                    'middlewares' => 'ProxyHeaders|SetHost:api.example.com|Timeout:30',
                ],
            ],
        ]));

        $routes = $this->loadRoutes(new FileLoader([$filePath]));

        $middlewares = $routes[0]->getMiddlewares();
        $this->assertCount(3, $middlewares);

        $classes = array_map('get_class', $middlewares);
        $this->assertContains(ProxyHeaders::class, $classes);
    }

    public function test_returns_empty_array_when_no_paths(): void
    {
        $routes = $this->loadRoutes(new FileLoader([]));

        $this->assertIsArray($routes);
        $this->assertEmpty($routes);
    }

    public function test_get_cache_key_returns_null_for_empty_paths(): void
    {
        $loader = new FileLoader([]);

        $this->assertNull($loader->getCacheKey());
    }

    public function test_get_cache_key_returns_consistent_key_for_same_paths(): void
    {
        $filePath = $this->fixturesPath . '/routes.json';
        file_put_contents($filePath, json_encode(['routes' => []]));

        $loader1 = new FileLoader([$filePath]);
        $loader2 = new FileLoader([$filePath]);

        $this->assertNotNull($loader1->getCacheKey());
        $this->assertEquals($loader1->getCacheKey(), $loader2->getCacheKey());
    }

    public function test_cache_invalidation_when_file_modified(): void
    {
        $filePath = $this->fixturesPath . '/routes.json';
        file_put_contents($filePath, json_encode([
            'routes' => [
                ['path' => '/api/*', 'target' => 'https://api.example.com'],
            ],
        ]));

        $loader = new FileLoader([$filePath]);
        $originalMtime = $loader->getCacheMetadata();

        // Simulate cache with old mtime
        $this->assertTrue($loader->isCacheValid($originalMtime));

        // Modify file (touch to update mtime)
        sleep(1);
        touch($filePath);
        clearstatcache();

        // Cache should now be invalid
        $this->assertFalse($loader->isCacheValid($originalMtime));
    }

    public function test_reloads_from_loader_when_cache_is_stale(): void
    {
        $filePath = $this->fixturesPath . '/routes.json';
        file_put_contents($filePath, json_encode([
            'routes' => [
                ['path' => '/api/*', 'target' => 'https://api.example.com'],
            ],
        ]));

        $loader = new FileLoader([$filePath]);
        $cacheKey = $loader->getCacheKey();

        // Simulate stale cache with old mtime
        $staleMtime = 12345;

        $cache = new ArrayCache();
        $cache->set($cacheKey, [
            'metadata' => $staleMtime,
            'data' => [['path' => '/cached/*', 'target' => 'https://cached.example.com']],
        ]);

        $collection = new RouteCollection([$loader], $cache);
        $collection->load();

        // Should reload from file, not use stale cache
        $this->assertCount(1, $collection);
        $this->assertEquals('api.example.com', $collection[0]->getTargetHost());

        // Verify cache was updated with new data
        $cachedData = $cache->get($cacheKey);
        $this->assertNotNull($cachedData);
        $this->assertNotEquals($staleMtime, $cachedData['metadata']);
    }

    public function test_skips_disabled_routes(): void
    {
        $filePath = $this->fixturesPath . '/routes.json';
        file_put_contents($filePath, json_encode([
            'routes' => [
                [
                    'path' => '/enabled/*',
                    'target' => 'https://enabled.example.com',
                    'enabled' => true,
                ],
                [
                    'path' => '/disabled/*',
                    'target' => 'https://disabled.example.com',
                    'enabled' => false,
                ],
                [
                    'path' => '/no-enabled-field/*',
                    'target' => 'https://no-enabled.example.com',
                    // no enabled field - should be included (default true)
                ],
            ],
        ]));

        $routes = $this->loadRoutes(new FileLoader([$filePath]));

        $this->assertCount(2, $routes);

        $hosts = array_map(function ($r) {
            return $r->getTargetHost();
        }, $routes);
        $this->assertContains('enabled.example.com', $hosts);
        $this->assertContains('no-enabled.example.com', $hosts);
        $this->assertNotContains('disabled.example.com', $hosts);
    }

    public function test_skips_disabled_routes_in_yaml(): void
    {
        $filePath = $this->fixturesPath . '/routes.yaml';
        $yaml = implode("\n", [
            'routes:',
            '  - path: /enabled/*',
            '    target: https://enabled.example.com',
            '    enabled: true',
            '  - path: /disabled/*',
            '    target: https://disabled.example.com',
            '    enabled: false',
        ]);
        file_put_contents($filePath, $yaml);

        $routes = $this->loadRoutes(new FileLoader([$filePath]));

        $this->assertCount(1, $routes);
        $this->assertEquals('enabled.example.com', $routes[0]->getTargetHost());
    }

    /**
     * Helper to load routes through RouteCollection.
     *
     * @return array<Route>
     */
    private function loadRoutes(FileLoader $loader): array
    {
        $collection = new RouteCollection([$loader]);

        return $collection->load()->all();
    }
}
