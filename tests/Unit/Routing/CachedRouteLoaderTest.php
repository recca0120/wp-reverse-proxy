<?php

namespace Recca0120\ReverseProxy\Tests\Unit\Routing;

use Mockery;
use PHPUnit\Framework\TestCase;
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
        $cache = new ArrayCache();

        $loader = new CachedRouteLoader($innerLoader, $cache);

        $this->assertInstanceOf(RouteLoaderInterface::class, $loader);
    }

    public function test_it_delegates_to_inner_loader_when_fingerprint_is_null(): void
    {
        $expectedData = [['path' => '/api/*', 'target' => 'https://api.example.com']];

        $innerLoader = Mockery::mock(RouteLoaderInterface::class);
        $innerLoader->shouldReceive('getFingerprint')->andReturn(null);
        $innerLoader->shouldReceive('load')->once()->andReturn($expectedData);

        $cache = new ArrayCache();

        $loader = new CachedRouteLoader($innerLoader, $cache);
        $result = $loader->load();

        $this->assertEquals($expectedData, $result);
        $this->assertEmpty($cache->all());
    }

    public function test_it_returns_cached_data_when_fingerprint_matches(): void
    {
        $identifier = 'test_identifier';
        $fingerprint = 'test_fingerprint_12345';
        $cacheKey = 'route_loader_' . $identifier;
        $cachedData = [['path' => '/cached/*', 'target' => 'https://cached.example.com']];

        $innerLoader = Mockery::mock(RouteLoaderInterface::class);
        $innerLoader->shouldReceive('getIdentifier')->andReturn($identifier);
        $innerLoader->shouldReceive('getFingerprint')->andReturn($fingerprint);
        $innerLoader->shouldNotReceive('load');

        $cache = new ArrayCache();
        $cache->set($cacheKey, ['fingerprint' => $fingerprint, 'data' => $cachedData]);

        $loader = new CachedRouteLoader($innerLoader, $cache);

        $this->assertEquals($cachedData, $loader->load());
    }

    public function test_it_loads_from_inner_loader_when_cache_is_empty(): void
    {
        $identifier = 'test_identifier';
        $fingerprint = 'test_fingerprint_12345';
        $cacheKey = 'route_loader_' . $identifier;
        $expectedData = [['path' => '/api/*', 'target' => 'https://api.example.com']];

        $innerLoader = Mockery::mock(RouteLoaderInterface::class);
        $innerLoader->shouldReceive('getIdentifier')->andReturn($identifier);
        $innerLoader->shouldReceive('getFingerprint')->andReturn($fingerprint);
        $innerLoader->shouldReceive('load')->once()->andReturn($expectedData);

        $cache = new ArrayCache();

        $loader = new CachedRouteLoader($innerLoader, $cache);
        $result = $loader->load();

        $this->assertEquals($expectedData, $result);
        $this->assertTrue($cache->has($cacheKey));
        $this->assertEquals($fingerprint, $cache->get($cacheKey)['fingerprint']);
    }

    public function test_it_reloads_from_inner_loader_when_fingerprint_changed(): void
    {
        $identifier = 'test_identifier';
        $oldFingerprint = 'old_fingerprint_12345';
        $newFingerprint = 'new_fingerprint_67890';
        $cacheKey = 'route_loader_' . $identifier;
        $staleData = [['path' => '/stale/*', 'target' => 'https://stale.example.com']];
        $freshData = [['path' => '/fresh/*', 'target' => 'https://fresh.example.com']];

        $innerLoader = Mockery::mock(RouteLoaderInterface::class);
        $innerLoader->shouldReceive('getIdentifier')->andReturn($identifier);
        $innerLoader->shouldReceive('getFingerprint')->andReturn($newFingerprint);
        $innerLoader->shouldReceive('load')->once()->andReturn($freshData);

        $cache = new ArrayCache();
        $cache->set($cacheKey, ['fingerprint' => $oldFingerprint, 'data' => $staleData]);

        $loader = new CachedRouteLoader($innerLoader, $cache);
        $result = $loader->load();

        $this->assertEquals($freshData, $result);
        $this->assertEquals($newFingerprint, $cache->get($cacheKey)['fingerprint']);
    }

    public function test_it_delegates_get_identifier_to_inner_loader(): void
    {
        $identifier = 'inner_identifier_12345';

        $innerLoader = Mockery::mock(RouteLoaderInterface::class);
        $innerLoader->shouldReceive('getIdentifier')->once()->andReturn($identifier);

        $cache = new ArrayCache();

        $loader = new CachedRouteLoader($innerLoader, $cache);

        $this->assertEquals($identifier, $loader->getIdentifier());
    }

    public function test_it_delegates_get_fingerprint_to_inner_loader(): void
    {
        $fingerprint = 'inner_fingerprint_12345';

        $innerLoader = Mockery::mock(RouteLoaderInterface::class);
        $innerLoader->shouldReceive('getFingerprint')->once()->andReturn($fingerprint);

        $cache = new ArrayCache();

        $loader = new CachedRouteLoader($innerLoader, $cache);

        $this->assertEquals($fingerprint, $loader->getFingerprint());
    }

    public function test_clear_cache_deletes_cache_entry(): void
    {
        $identifier = 'test_identifier';
        $fingerprint = 'test_fingerprint_12345';
        $cacheKey = 'route_loader_' . $identifier;

        $innerLoader = Mockery::mock(RouteLoaderInterface::class);
        $innerLoader->shouldReceive('getIdentifier')->andReturn($identifier);

        $cache = new ArrayCache();
        $cache->set($cacheKey, ['fingerprint' => $fingerprint, 'data' => []]);

        $loader = new CachedRouteLoader($innerLoader, $cache);
        $loader->clearCache();

        $this->assertFalse($cache->has($cacheKey));
    }

    public function test_cache_key_is_stable_when_fingerprint_changes(): void
    {
        $identifier = 'stable_identifier';
        $cacheKey = 'route_loader_' . $identifier;

        $innerLoader = Mockery::mock(RouteLoaderInterface::class);
        $innerLoader->shouldReceive('getIdentifier')->andReturn($identifier);
        $innerLoader->shouldReceive('getFingerprint')->andReturn('fingerprint_v1');
        $innerLoader->shouldReceive('load')->once()->andReturn([['path' => '/v1/*', 'target' => 'https://v1.example.com']]);

        $cache = new ArrayCache();
        $loader = new CachedRouteLoader($innerLoader, $cache);

        // First load - populates cache
        $loader->load();
        $this->assertTrue($cache->has($cacheKey));
        $this->assertEquals('fingerprint_v1', $cache->get($cacheKey)['fingerprint']);

        // Simulate fingerprint change (e.g., file modified)
        $innerLoader2 = Mockery::mock(RouteLoaderInterface::class);
        $innerLoader2->shouldReceive('getIdentifier')->andReturn($identifier);
        $innerLoader2->shouldReceive('getFingerprint')->andReturn('fingerprint_v2');
        $innerLoader2->shouldReceive('load')->once()->andReturn([['path' => '/v2/*', 'target' => 'https://v2.example.com']]);

        $loader2 = new CachedRouteLoader($innerLoader2, $cache);
        $result = $loader2->load();

        // Same cache key, but updated data
        $this->assertTrue($cache->has($cacheKey));
        $this->assertEquals('fingerprint_v2', $cache->get($cacheKey)['fingerprint']);
        $this->assertEquals([['path' => '/v2/*', 'target' => 'https://v2.example.com']], $result);
    }
}
