<?php

namespace Recca0120\ReverseProxy\Tests\Integration\Routing;

use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Recca0120\ReverseProxy\Routing\FileLoader;
use Recca0120\ReverseProxy\Routing\RouteCollection;
use Recca0120\ReverseProxy\Tests\Stubs\ArrayCache;

class RouteCollectionTest extends TestCase
{
    private $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/reverse-proxy-test-' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    public function test_inject_cache_into_rate_limiting_middleware(): void
    {
        $this->createRouteFile('routes.json', [
            'routes' => [
                ['path' => '/api/*', 'target' => 'https://api.example.com', 'middlewares' => ['RateLimiting:100,60']],
            ],
        ]);

        $cache = new ArrayCache();
        $collection = new RouteCollection(
            [new FileLoader([$this->tempDir])],
            $cache
        );

        $routes = $collection->all();
        $middleware = $routes[0]->getMiddlewares()[0];

        $request = new ServerRequest('GET', 'https://example.com/test', [], null, '1.1', ['REMOTE_ADDR' => '127.0.0.1']);
        $middleware->process($request, function () {
            return new Response(200, [], 'OK');
        });

        $this->assertNotEmpty($cache->all());
    }

    public function test_inject_cache_into_caching_middleware(): void
    {
        $this->createRouteFile('routes.json', [
            'routes' => [
                ['path' => '/api/*', 'target' => 'https://api.example.com', 'middlewares' => ['Caching:300']],
            ],
        ]);

        $cache = new ArrayCache();
        $collection = new RouteCollection(
            [new FileLoader([$this->tempDir])],
            $cache
        );

        $routes = $collection->all();
        $middleware = $routes[0]->getMiddlewares()[0];

        $request = new ServerRequest('GET', 'https://example.com/test');
        $middleware->process($request, function () {
            return new Response(200, [], 'OK');
        });

        $this->assertNotEmpty($cache->all());
    }

    public function test_inject_cache_into_circuit_breaker_middleware(): void
    {
        $this->createRouteFile('routes.json', [
            'routes' => [
                ['path' => '/api/*', 'target' => 'https://api.example.com', 'middlewares' => [['CircuitBreaker', 'my-service']]],
            ],
        ]);

        $cache = new ArrayCache();
        $collection = new RouteCollection(
            [new FileLoader([$this->tempDir])],
            $cache
        );

        $routes = $collection->all();
        $middleware = $routes[0]->getMiddlewares()[0];

        $request = new ServerRequest('GET', 'https://example.com/test');
        $middleware->process($request, function () {
            return new Response(200, [], 'OK');
        });

        $this->assertNotEmpty($cache->all());
    }

    private function createRouteFile(string $filename, array $content): void
    {
        file_put_contents(
            $this->tempDir . '/' . $filename,
            json_encode($content, JSON_PRETTY_PRINT)
        );
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
