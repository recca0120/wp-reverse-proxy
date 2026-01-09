<?php

namespace Recca0120\ReverseProxy\Tests\Unit\Routing;

use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use Recca0120\ReverseProxy\Contracts\RouteLoaderInterface;
use Recca0120\ReverseProxy\Middleware\ProxyHeaders;
use Recca0120\ReverseProxy\Routing\FileLoader;
use Recca0120\ReverseProxy\Routing\Route;
use Recca0120\ReverseProxy\Routing\RouteCollection;

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

    public function test_merge_routes_from_multiple_files(): void
    {
        file_put_contents($this->fixturesPath . '/first.json', json_encode([
            'routes' => [
                ['path' => '/first/*', 'target' => 'https://first.example.com'],
            ],
        ]));

        file_put_contents($this->fixturesPath . '/second.json', json_encode([
            'routes' => [
                ['path' => '/second/*', 'target' => 'https://second.example.com'],
            ],
        ]));

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

    public function test_create_route_with_path_and_target(): void
    {
        $filePath = $this->fixturesPath . '/routes.json';
        file_put_contents($filePath, json_encode([
            'routes' => [
                [
                    'path' => '/api/users/*',
                    'target' => 'https://api.example.com',
                ],
            ],
        ]));

        $routes = $this->loadRoutes(new FileLoader([$filePath]));

        $this->assertCount(1, $routes);
        $this->assertEquals('api.example.com', $routes[0]->getTargetHost());
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

    public function test_uses_cache_when_available(): void
    {
        $filePath = $this->fixturesPath . '/routes.json';
        file_put_contents($filePath, json_encode([
            'routes' => [
                ['path' => '/api/*', 'target' => 'https://api.example.com'],
            ],
        ]));

        $cachedConfigs = [
            ['path' => '/cached/*', 'target' => 'https://cached.example.com'],
        ];

        $cache = Mockery::mock(CacheInterface::class);
        $cache->shouldReceive('get')
            ->with('route_configs')
            ->once()
            ->andReturn($cachedConfigs);

        $collection = new RouteCollection([new FileLoader([$filePath])], null, $cache);
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

        $cache = Mockery::mock(CacheInterface::class);
        $cache->shouldReceive('get')->with('route_configs')->once()->andReturnNull();
        $cache->shouldReceive('set')->with('route_configs', Mockery::type('array'))->once();

        $collection = new RouteCollection([new FileLoader([$filePath])], null, $cache);
        $collection->load();

        $this->assertCount(1, $collection);
    }

    public function test_works_without_cache(): void
    {
        $filePath = $this->fixturesPath . '/routes.json';
        file_put_contents($filePath, json_encode([
            'routes' => [
                ['path' => '/api/*', 'target' => 'https://api.example.com'],
            ],
        ]));

        $routes = $this->loadRoutes(new FileLoader([$filePath]));

        $this->assertCount(1, $routes);
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
