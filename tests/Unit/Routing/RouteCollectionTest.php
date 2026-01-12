<?php

namespace Recca0120\ReverseProxy\Tests\Unit\Routing;

use Mockery;
use PHPUnit\Framework\TestCase;
use Recca0120\ReverseProxy\Contracts\RouteLoaderInterface;
use Recca0120\ReverseProxy\Routing\Route;
use Recca0120\ReverseProxy\Routing\RouteCollection;
use Recca0120\ReverseProxy\Tests\Stubs\ArrayCache;

class RouteCollectionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function test_can_add_single_route(): void
    {
        $collection = new RouteCollection();
        $route = new Route('/api/*', 'https://api.example.com');

        $collection->add($route);

        $this->assertCount(1, $collection);
        $this->assertSame($route, $collection[0]);
    }

    public function test_can_add_multiple_routes(): void
    {
        $collection = new RouteCollection();
        $routes = [
            new Route('/api/*', 'https://api.example.com'),
            new Route('/web/*', 'https://web.example.com'),
        ];

        $collection->add($routes);

        $this->assertCount(2, $collection);
    }

    public function test_all_returns_all_routes(): void
    {
        $collection = new RouteCollection();
        $route1 = new Route('/api/*', 'https://api.example.com');
        $route2 = new Route('/web/*', 'https://web.example.com');

        $collection->add($route1)->add($route2);

        $all = $collection->all();

        $this->assertCount(2, $all);
        $this->assertSame($route1, $all[0]);
        $this->assertSame($route2, $all[1]);
    }

    public function test_is_countable(): void
    {
        $collection = new RouteCollection();
        $collection->add(new Route('/api/*', 'https://api.example.com'));
        $collection->add(new Route('/web/*', 'https://web.example.com'));

        $this->assertCount(2, $collection);
        $this->assertEquals(2, count($collection));
    }

    public function test_is_iterable(): void
    {
        $collection = new RouteCollection();
        $collection->add(new Route('/api/*', 'https://api.example.com'));
        $collection->add(new Route('/web/*', 'https://web.example.com'));

        $count = 0;
        foreach ($collection as $route) {
            $this->assertInstanceOf(Route::class, $route);
            $count++;
        }

        $this->assertEquals(2, $count);
    }

    public function test_array_access_exists(): void
    {
        $collection = new RouteCollection();
        $collection->add(new Route('/api/*', 'https://api.example.com'));

        $this->assertTrue(isset($collection[0]));
        $this->assertFalse(isset($collection[1]));
    }

    public function test_array_access_get(): void
    {
        $collection = new RouteCollection();
        $route = new Route('/api/*', 'https://api.example.com');
        $collection->add($route);

        $this->assertSame($route, $collection[0]);
        $this->assertNull($collection[1]);
    }

    public function test_array_access_set(): void
    {
        $collection = new RouteCollection();
        $route = new Route('/api/*', 'https://api.example.com');

        $collection[] = $route;

        $this->assertSame($route, $collection[0]);
    }

    public function test_array_access_unset(): void
    {
        $collection = new RouteCollection();
        $collection->add(new Route('/api/*', 'https://api.example.com'));

        unset($collection[0]);

        $this->assertFalse(isset($collection[0]));
    }

    public function test_load_from_single_loader(): void
    {
        $loader = Mockery::mock(RouteLoaderInterface::class);
        $loader->shouldReceive('getCacheKey')->andReturnNull();
        $loader->shouldReceive('load')->once()->andReturn([
            ['path' => '/api/*', 'target' => 'https://api.example.com'],
        ]);

        $collection = new RouteCollection([$loader]);
        $collection->load();

        $this->assertCount(1, $collection);
        $this->assertEquals('api.example.com', $collection[0]->getTargetHost());
    }

    public function test_load_from_multiple_loaders(): void
    {
        $loader1 = Mockery::mock(RouteLoaderInterface::class);
        $loader1->shouldReceive('getCacheKey')->andReturnNull();
        $loader1->shouldReceive('load')->once()->andReturn([
            ['path' => '/api/*', 'target' => 'https://api.example.com'],
        ]);

        $loader2 = Mockery::mock(RouteLoaderInterface::class);
        $loader2->shouldReceive('getCacheKey')->andReturnNull();
        $loader2->shouldReceive('load')->once()->andReturn([
            ['path' => '/web/*', 'target' => 'https://web.example.com'],
        ]);

        $collection = new RouteCollection([$loader1, $loader2]);
        $collection->load();

        $this->assertCount(2, $collection);
    }

    public function test_load_skips_invalid_routes(): void
    {
        $loader = Mockery::mock(RouteLoaderInterface::class);
        $loader->shouldReceive('getCacheKey')->andReturnNull();
        $loader->shouldReceive('load')->once()->andReturn([
            ['target' => 'https://api.example.com'], // missing path
            ['path' => '/valid/*', 'target' => 'https://valid.example.com'],
        ]);

        $collection = new RouteCollection([$loader]);
        $collection->load();

        $this->assertCount(1, $collection);
        $this->assertEquals('valid.example.com', $collection[0]->getTargetHost());
    }

    public function test_load_uses_cache_when_available(): void
    {
        $cachedConfigs = [
            ['path' => '/cached/*', 'target' => 'https://cached.example.com'],
        ];

        $loader = Mockery::mock(RouteLoaderInterface::class);
        $loader->shouldReceive('getCacheKey')->andReturn('test_loader_key');
        $loader->shouldReceive('isCacheValid')->with(12345)->andReturn(true);
        $loader->shouldNotReceive('load');

        $cache = new ArrayCache();
        $cache->set('test_loader_key', ['metadata' => 12345, 'data' => $cachedConfigs]);

        $collection = new RouteCollection([$loader], $cache);
        $collection->load();

        $this->assertCount(1, $collection);
        $this->assertEquals('cached.example.com', $collection[0]->getTargetHost());
    }

    public function test_load_stores_cache_when_not_cached(): void
    {
        $loader = Mockery::mock(RouteLoaderInterface::class);
        $loader->shouldReceive('getCacheKey')->andReturn('test_loader_key');
        $loader->shouldReceive('getCacheMetadata')->andReturn(12345);
        $loader->shouldReceive('load')->once()->andReturn([
            ['path' => '/api/*', 'target' => 'https://api.example.com'],
        ]);

        $cache = new ArrayCache();

        $collection = new RouteCollection([$loader], $cache);
        $collection->load();

        $this->assertCount(1, $collection);

        $cachedData = $cache->get('test_loader_key');
        $this->assertNotNull($cachedData);
        $this->assertArrayHasKey('metadata', $cachedData);
        $this->assertArrayHasKey('data', $cachedData);
    }

    public function test_clear_cache(): void
    {
        $loader = Mockery::mock(RouteLoaderInterface::class);
        $loader->shouldReceive('getCacheKey')->andReturn('test_loader_key');

        $cache = new ArrayCache();
        $cache->set('test_loader_key', ['metadata' => 12345, 'data' => []]);

        $collection = new RouteCollection([$loader], $cache);
        $collection->clearCache();

        $this->assertFalse($cache->has('test_loader_key'));
    }

    public function test_clear_cache_without_cache_does_nothing(): void
    {
        $collection = new RouteCollection();
        $collection->clearCache(); // Should not throw

        $this->assertTrue(true);
    }

    public function test_load_returns_self_for_chaining(): void
    {
        $collection = new RouteCollection();
        $result = $collection->load();

        $this->assertSame($collection, $result);
    }

    public function test_add_returns_self_for_chaining(): void
    {
        $collection = new RouteCollection();
        $result = $collection->add(new Route('/api/*', 'https://api.example.com'));

        $this->assertSame($collection, $result);
    }

    public function test_array_access_set_with_index(): void
    {
        $collection = new RouteCollection();
        $route1 = new Route('/api/*', 'https://api.example.com');
        $route2 = new Route('/web/*', 'https://web.example.com');

        $collection[0] = $route1;
        $collection[5] = $route2;

        $this->assertSame($route1, $collection[0]);
        $this->assertSame($route2, $collection[5]);
    }

    public function test_load_reloads_when_cache_is_invalid(): void
    {
        $loader = Mockery::mock(RouteLoaderInterface::class);
        $loader->shouldReceive('getCacheKey')->andReturn('test_loader_key');
        $loader->shouldReceive('isCacheValid')->with(12345)->andReturn(false);
        $loader->shouldReceive('getCacheMetadata')->andReturn(99999);
        $loader->shouldReceive('load')->once()->andReturn([
            ['path' => '/fresh/*', 'target' => 'https://fresh.example.com'],
        ]);

        $cache = new ArrayCache();
        $cache->set('test_loader_key', ['metadata' => 12345, 'data' => [
            ['path' => '/stale/*', 'target' => 'https://stale.example.com'],
        ]]);

        $collection = new RouteCollection([$loader], $cache);
        $collection->load();

        $this->assertCount(1, $collection);
        $this->assertEquals('fresh.example.com', $collection[0]->getTargetHost());
    }

    public function test_load_skips_cache_for_loader_without_cache_key(): void
    {
        $loader = Mockery::mock(RouteLoaderInterface::class);
        $loader->shouldReceive('getCacheKey')->andReturn(null);
        $loader->shouldReceive('load')->once()->andReturn([
            ['path' => '/api/*', 'target' => 'https://api.example.com'],
        ]);

        $cache = new ArrayCache();
        $collection = new RouteCollection([$loader], $cache);
        $collection->load();

        $this->assertCount(1, $collection);
        // Cache should not have any entries since loader has no cache key
        $this->assertEmpty($cache->all());
    }

    public function test_clear_cache_with_multiple_loaders(): void
    {
        $loader1 = Mockery::mock(RouteLoaderInterface::class);
        $loader1->shouldReceive('getCacheKey')->andReturn('loader1_key');

        $loader2 = Mockery::mock(RouteLoaderInterface::class);
        $loader2->shouldReceive('getCacheKey')->andReturn('loader2_key');

        $cache = new ArrayCache();
        $cache->set('loader1_key', ['metadata' => 1, 'data' => []]);
        $cache->set('loader2_key', ['metadata' => 2, 'data' => []]);

        $collection = new RouteCollection([$loader1, $loader2], $cache);
        $collection->clearCache();

        $this->assertFalse($cache->has('loader1_key'));
        $this->assertFalse($cache->has('loader2_key'));
    }

}
