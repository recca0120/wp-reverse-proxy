<?php

namespace Recca0120\ReverseProxy\Tests\Unit\Routing;

use PHPUnit\Framework\TestCase;
use Recca0120\ReverseProxy\Contracts\RouteLoaderInterface;
use Recca0120\ReverseProxy\Routing\CachedRouteLoader;
use Recca0120\ReverseProxy\Tests\Stubs\ArrayCache;
use Recca0120\ReverseProxy\Tests\Stubs\FakeRouteLoader;

class CachedRouteLoaderTest extends TestCase
{
    public function test_implements_route_loader_interface(): void
    {
        $innerLoader = new FakeRouteLoader();
        $cache = new ArrayCache();

        $loader = new CachedRouteLoader($innerLoader, $cache);

        $this->assertInstanceOf(RouteLoaderInterface::class, $loader);
    }

    public function test_bypasses_cache_when_fingerprint_null(): void
    {
        $expectedData = [['path' => '/api/*', 'target' => 'https://api.example.com']];

        $innerLoader = (new FakeRouteLoader())
            ->setFingerprint(null)
            ->setRoutes($expectedData);

        $cache = new ArrayCache();

        $loader = new CachedRouteLoader($innerLoader, $cache);
        $result = $loader->load();

        $this->assertEquals($expectedData, $result);
        $this->assertEmpty($cache->all());
        $this->assertEquals(1, $innerLoader->getLoadCallCount());
    }

    public function test_cache_hit_when_fingerprint_matches(): void
    {
        $identifier = 'test_identifier';
        $fingerprint = 'test_fingerprint_12345';
        $cacheKey = 'route_loader_' . $identifier;
        $cachedData = [['path' => '/cached/*', 'target' => 'https://cached.example.com']];

        $innerLoader = (new FakeRouteLoader())
            ->setIdentifier($identifier)
            ->setFingerprint($fingerprint);

        $cache = new ArrayCache();
        $cache->set($cacheKey, ['fingerprint' => $fingerprint, 'data' => $cachedData]);

        $loader = new CachedRouteLoader($innerLoader, $cache);

        $this->assertEquals($cachedData, $loader->load());
        $this->assertEquals(0, $innerLoader->getLoadCallCount(), 'load() should not be called on cache hit');
    }

    public function test_cache_miss_when_cache_empty(): void
    {
        $identifier = 'test_identifier';
        $fingerprint = 'test_fingerprint_12345';
        $cacheKey = 'route_loader_' . $identifier;
        $expectedData = [['path' => '/api/*', 'target' => 'https://api.example.com']];

        $innerLoader = (new FakeRouteLoader())
            ->setIdentifier($identifier)
            ->setFingerprint($fingerprint)
            ->setRoutes($expectedData);

        $cache = new ArrayCache();

        $loader = new CachedRouteLoader($innerLoader, $cache);
        $result = $loader->load();

        $this->assertEquals($expectedData, $result);
        $this->assertTrue($cache->has($cacheKey));
        $this->assertEquals($fingerprint, $cache->get($cacheKey)['fingerprint']);
        $this->assertEquals(1, $innerLoader->getLoadCallCount());
    }

    public function test_cache_invalidation_when_fingerprint_changed(): void
    {
        $identifier = 'test_identifier';
        $oldFingerprint = 'old_fingerprint_12345';
        $newFingerprint = 'new_fingerprint_67890';
        $cacheKey = 'route_loader_' . $identifier;
        $staleData = [['path' => '/stale/*', 'target' => 'https://stale.example.com']];
        $freshData = [['path' => '/fresh/*', 'target' => 'https://fresh.example.com']];

        $innerLoader = (new FakeRouteLoader())
            ->setIdentifier($identifier)
            ->setFingerprint($newFingerprint)
            ->setRoutes($freshData);

        $cache = new ArrayCache();
        $cache->set($cacheKey, ['fingerprint' => $oldFingerprint, 'data' => $staleData]);

        $loader = new CachedRouteLoader($innerLoader, $cache);
        $result = $loader->load();

        $this->assertEquals($freshData, $result);
        $this->assertEquals($newFingerprint, $cache->get($cacheKey)['fingerprint']);
        $this->assertEquals(1, $innerLoader->getLoadCallCount());
    }

    public function test_delegates_identifier_to_inner_loader(): void
    {
        $identifier = 'inner_identifier_12345';

        $innerLoader = (new FakeRouteLoader())
            ->setIdentifier($identifier);

        $cache = new ArrayCache();

        $loader = new CachedRouteLoader($innerLoader, $cache);

        $this->assertEquals($identifier, $loader->getIdentifier());
    }

    public function test_delegates_fingerprint_to_inner_loader(): void
    {
        $fingerprint = 'inner_fingerprint_12345';

        $innerLoader = (new FakeRouteLoader())
            ->setFingerprint($fingerprint);

        $cache = new ArrayCache();

        $loader = new CachedRouteLoader($innerLoader, $cache);

        $this->assertEquals($fingerprint, $loader->getFingerprint());
    }

    public function test_clear_cache_deletes_cache_entry(): void
    {
        $identifier = 'test_identifier';
        $fingerprint = 'test_fingerprint_12345';
        $cacheKey = 'route_loader_' . $identifier;

        $innerLoader = (new FakeRouteLoader())
            ->setIdentifier($identifier);

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

        $innerLoader = (new FakeRouteLoader())
            ->setIdentifier($identifier)
            ->setFingerprint('fingerprint_v1')
            ->setRoutes([['path' => '/v1/*', 'target' => 'https://v1.example.com']]);

        $cache = new ArrayCache();
        $loader = new CachedRouteLoader($innerLoader, $cache);

        // First load - populates cache
        $loader->load();
        $this->assertTrue($cache->has($cacheKey));
        $this->assertEquals('fingerprint_v1', $cache->get($cacheKey)['fingerprint']);
        $this->assertEquals(1, $innerLoader->getLoadCallCount());

        // Simulate fingerprint change (e.g., file modified)
        $innerLoader2 = (new FakeRouteLoader())
            ->setIdentifier($identifier)
            ->setFingerprint('fingerprint_v2')
            ->setRoutes([['path' => '/v2/*', 'target' => 'https://v2.example.com']]);

        $loader2 = new CachedRouteLoader($innerLoader2, $cache);
        $result = $loader2->load();

        // Same cache key, but updated data
        $this->assertTrue($cache->has($cacheKey));
        $this->assertEquals('fingerprint_v2', $cache->get($cacheKey)['fingerprint']);
        $this->assertEquals([['path' => '/v2/*', 'target' => 'https://v2.example.com']], $result);
        $this->assertEquals(1, $innerLoader2->getLoadCallCount());
    }
}
