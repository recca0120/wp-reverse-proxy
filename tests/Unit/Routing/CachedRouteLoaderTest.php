<?php

namespace Recca0120\ReverseProxy\Tests\Unit\Routing;

use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use Recca0120\ReverseProxy\Contracts\RouteLoaderInterface;
use Recca0120\ReverseProxy\Routing\CachedRouteLoader;
use Recca0120\ReverseProxy\Tests\Stubs\ArrayCache;

class CachedRouteLoaderTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function test_it_implements_route_loader_interface(): void
    {
        $innerLoader = Mockery::mock(RouteLoaderInterface::class);
        $cache = Mockery::mock(CacheInterface::class);

        $loader = new CachedRouteLoader($innerLoader, $cache);

        $this->assertInstanceOf(RouteLoaderInterface::class, $loader);
    }

    public function test_it_delegates_to_inner_loader_when_fingerprint_is_null(): void
    {
        $expectedData = [['path' => '/api/*', 'target' => 'https://api.example.com']];

        $innerLoader = Mockery::mock(RouteLoaderInterface::class);
        $innerLoader->shouldReceive('getFingerprint')->once()->andReturn(null);
        $innerLoader->shouldReceive('load')->once()->andReturn($expectedData);

        $cache = Mockery::mock(CacheInterface::class);
        $cache->shouldNotReceive('get');
        $cache->shouldNotReceive('set');

        $loader = new CachedRouteLoader($innerLoader, $cache);

        $this->assertEquals($expectedData, $loader->load());
    }

    public function test_it_returns_cached_data_when_fingerprint_matches(): void
    {
        $fingerprint = 'test_fingerprint_12345';
        $cacheKey = 'route_loader_' . md5($fingerprint);
        $cachedData = [['path' => '/cached/*', 'target' => 'https://cached.example.com']];

        $innerLoader = Mockery::mock(RouteLoaderInterface::class);
        $innerLoader->shouldReceive('getFingerprint')->andReturn($fingerprint);
        $innerLoader->shouldNotReceive('load');

        $cache = Mockery::mock(CacheInterface::class);
        $cache->shouldReceive('get')
            ->with($cacheKey)
            ->once()
            ->andReturn(['fingerprint' => $fingerprint, 'data' => $cachedData]);
        $cache->shouldNotReceive('set');

        $loader = new CachedRouteLoader($innerLoader, $cache);

        $this->assertEquals($cachedData, $loader->load());
    }

    public function test_it_loads_from_inner_loader_when_cache_is_empty(): void
    {
        $fingerprint = 'test_fingerprint_12345';
        $cacheKey = 'route_loader_' . md5($fingerprint);
        $expectedData = [['path' => '/api/*', 'target' => 'https://api.example.com']];

        $innerLoader = Mockery::mock(RouteLoaderInterface::class);
        $innerLoader->shouldReceive('getFingerprint')->andReturn($fingerprint);
        $innerLoader->shouldReceive('load')->once()->andReturn($expectedData);

        $cache = Mockery::mock(CacheInterface::class);
        $cache->shouldReceive('get')->with($cacheKey)->once()->andReturn(null);
        $cache->shouldReceive('set')
            ->with($cacheKey, ['fingerprint' => $fingerprint, 'data' => $expectedData])
            ->once();

        $loader = new CachedRouteLoader($innerLoader, $cache);

        $this->assertEquals($expectedData, $loader->load());
    }

    public function test_it_reloads_from_inner_loader_when_fingerprint_changed(): void
    {
        $oldFingerprint = 'old_fingerprint_12345';
        $newFingerprint = 'new_fingerprint_67890';
        $cacheKey = 'route_loader_' . md5($newFingerprint);
        $staleData = [['path' => '/stale/*', 'target' => 'https://stale.example.com']];
        $freshData = [['path' => '/fresh/*', 'target' => 'https://fresh.example.com']];

        $innerLoader = Mockery::mock(RouteLoaderInterface::class);
        $innerLoader->shouldReceive('getFingerprint')->andReturn($newFingerprint);
        $innerLoader->shouldReceive('load')->once()->andReturn($freshData);

        $cache = Mockery::mock(CacheInterface::class);
        $cache->shouldReceive('get')
            ->with($cacheKey)
            ->once()
            ->andReturn(['fingerprint' => $oldFingerprint, 'data' => $staleData]);
        $cache->shouldReceive('set')
            ->with($cacheKey, ['fingerprint' => $newFingerprint, 'data' => $freshData])
            ->once();

        $loader = new CachedRouteLoader($innerLoader, $cache);

        $this->assertEquals($freshData, $loader->load());
    }

    public function test_it_delegates_get_fingerprint_to_inner_loader(): void
    {
        $fingerprint = 'inner_fingerprint_12345';

        $innerLoader = Mockery::mock(RouteLoaderInterface::class);
        $innerLoader->shouldReceive('getFingerprint')->once()->andReturn($fingerprint);

        $cache = Mockery::mock(CacheInterface::class);

        $loader = new CachedRouteLoader($innerLoader, $cache);

        $this->assertEquals($fingerprint, $loader->getFingerprint());
    }

    public function test_clear_cache_does_nothing_when_fingerprint_is_null(): void
    {
        $innerLoader = Mockery::mock(RouteLoaderInterface::class);
        $innerLoader->shouldReceive('getFingerprint')->andReturn(null);

        $cache = new ArrayCache();
        $cache->set('some_key', 'some_value');

        $loader = new CachedRouteLoader($innerLoader, $cache);
        $loader->clearCache();

        // Cache should remain unchanged since fingerprint is null
        $this->assertTrue($cache->has('some_key'));
    }

    public function test_clear_cache_deletes_cache_entry(): void
    {
        $fingerprint = 'test_fingerprint_12345';
        $cacheKey = 'route_loader_' . md5($fingerprint);

        $innerLoader = Mockery::mock(RouteLoaderInterface::class);
        $innerLoader->shouldReceive('getFingerprint')->andReturn($fingerprint);

        $cache = new ArrayCache();
        $cache->set($cacheKey, ['fingerprint' => $fingerprint, 'data' => []]);

        $loader = new CachedRouteLoader($innerLoader, $cache);
        $loader->clearCache();

        $this->assertFalse($cache->has($cacheKey));
    }
}
