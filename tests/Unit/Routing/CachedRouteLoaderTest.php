<?php

namespace Recca0120\ReverseProxy\Tests\Unit\Routing;

use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use Recca0120\ReverseProxy\Contracts\RouteLoaderInterface;
use Recca0120\ReverseProxy\Routing\CachedRouteLoader;

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

    public function test_it_delegates_to_inner_loader_when_cache_key_is_null(): void
    {
        $expectedData = [['path' => '/api/*', 'target' => 'https://api.example.com']];

        $innerLoader = Mockery::mock(RouteLoaderInterface::class);
        $innerLoader->shouldReceive('getCacheKey')->once()->andReturn(null);
        $innerLoader->shouldReceive('load')->once()->andReturn($expectedData);

        $cache = Mockery::mock(CacheInterface::class);
        $cache->shouldNotReceive('get');
        $cache->shouldNotReceive('set');

        $loader = new CachedRouteLoader($innerLoader, $cache);

        $this->assertEquals($expectedData, $loader->load());
    }

    public function test_it_returns_cached_data_when_cache_is_valid(): void
    {
        $cachedData = [['path' => '/cached/*', 'target' => 'https://cached.example.com']];
        $metadata = ['mtime' => 12345];

        $innerLoader = Mockery::mock(RouteLoaderInterface::class);
        $innerLoader->shouldReceive('getCacheKey')->andReturn('test_cache_key');
        $innerLoader->shouldReceive('isCacheValid')->with($metadata)->once()->andReturn(true);
        $innerLoader->shouldNotReceive('load');

        $cache = Mockery::mock(CacheInterface::class);
        $cache->shouldReceive('get')
            ->with('test_cache_key')
            ->once()
            ->andReturn(['metadata' => $metadata, 'data' => $cachedData]);
        $cache->shouldNotReceive('set');

        $loader = new CachedRouteLoader($innerLoader, $cache);

        $this->assertEquals($cachedData, $loader->load());
    }

    public function test_it_loads_from_inner_loader_when_cache_is_empty(): void
    {
        $expectedData = [['path' => '/api/*', 'target' => 'https://api.example.com']];
        $metadata = ['mtime' => 12345];

        $innerLoader = Mockery::mock(RouteLoaderInterface::class);
        $innerLoader->shouldReceive('getCacheKey')->andReturn('test_cache_key');
        $innerLoader->shouldReceive('load')->once()->andReturn($expectedData);
        $innerLoader->shouldReceive('getCacheMetadata')->once()->andReturn($metadata);

        $cache = Mockery::mock(CacheInterface::class);
        $cache->shouldReceive('get')->with('test_cache_key')->once()->andReturn(null);
        $cache->shouldReceive('set')
            ->with('test_cache_key', ['metadata' => $metadata, 'data' => $expectedData])
            ->once();

        $loader = new CachedRouteLoader($innerLoader, $cache);

        $this->assertEquals($expectedData, $loader->load());
    }

    public function test_it_reloads_from_inner_loader_when_cache_is_stale(): void
    {
        $staleData = [['path' => '/stale/*', 'target' => 'https://stale.example.com']];
        $freshData = [['path' => '/fresh/*', 'target' => 'https://fresh.example.com']];
        $staleMetadata = ['mtime' => 12345];
        $freshMetadata = ['mtime' => 67890];

        $innerLoader = Mockery::mock(RouteLoaderInterface::class);
        $innerLoader->shouldReceive('getCacheKey')->andReturn('test_cache_key');
        $innerLoader->shouldReceive('isCacheValid')->with($staleMetadata)->once()->andReturn(false);
        $innerLoader->shouldReceive('load')->once()->andReturn($freshData);
        $innerLoader->shouldReceive('getCacheMetadata')->once()->andReturn($freshMetadata);

        $cache = Mockery::mock(CacheInterface::class);
        $cache->shouldReceive('get')
            ->with('test_cache_key')
            ->once()
            ->andReturn(['metadata' => $staleMetadata, 'data' => $staleData]);
        $cache->shouldReceive('set')
            ->with('test_cache_key', ['metadata' => $freshMetadata, 'data' => $freshData])
            ->once();

        $loader = new CachedRouteLoader($innerLoader, $cache);

        $this->assertEquals($freshData, $loader->load());
    }

    public function test_it_delegates_get_cache_key_to_inner_loader(): void
    {
        $innerLoader = Mockery::mock(RouteLoaderInterface::class);
        $innerLoader->shouldReceive('getCacheKey')->once()->andReturn('inner_cache_key');

        $cache = Mockery::mock(CacheInterface::class);

        $loader = new CachedRouteLoader($innerLoader, $cache);

        $this->assertEquals('inner_cache_key', $loader->getCacheKey());
    }

    public function test_it_delegates_get_cache_metadata_to_inner_loader(): void
    {
        $metadata = ['mtime' => 12345, 'version' => '1.0'];

        $innerLoader = Mockery::mock(RouteLoaderInterface::class);
        $innerLoader->shouldReceive('getCacheMetadata')->once()->andReturn($metadata);

        $cache = Mockery::mock(CacheInterface::class);

        $loader = new CachedRouteLoader($innerLoader, $cache);

        $this->assertEquals($metadata, $loader->getCacheMetadata());
    }

    public function test_it_delegates_is_cache_valid_to_inner_loader(): void
    {
        $metadata = ['mtime' => 12345];

        $innerLoader = Mockery::mock(RouteLoaderInterface::class);
        $innerLoader->shouldReceive('isCacheValid')->with($metadata)->once()->andReturn(true);

        $cache = Mockery::mock(CacheInterface::class);

        $loader = new CachedRouteLoader($innerLoader, $cache);

        $this->assertTrue($loader->isCacheValid($metadata));
    }
}
