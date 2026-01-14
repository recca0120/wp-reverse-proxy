<?php

namespace Recca0120\ReverseProxy\Routing;

use Psr\SimpleCache\CacheInterface;
use Recca0120\ReverseProxy\Contracts\RouteLoaderInterface;

class CachedRouteLoader implements RouteLoaderInterface
{
    /** @var RouteLoaderInterface */
    private $loader;

    /** @var CacheInterface */
    private $cache;

    public function __construct(RouteLoaderInterface $loader, CacheInterface $cache)
    {
        $this->loader = $loader;
        $this->cache = $cache;
    }

    /**
     * Load route configurations with caching support.
     *
     * @return array<array<string, mixed>>
     */
    public function load(): array
    {
        $cacheKey = $this->loader->getCacheKey();

        if ($cacheKey === null) {
            return $this->loader->load();
        }

        $cached = $this->cache->get($cacheKey);

        if ($cached !== null && $this->loader->isCacheValid($cached['metadata'])) {
            return $cached['data'];
        }

        $data = $this->loader->load();
        $this->cache->set($cacheKey, [
            'metadata' => $this->loader->getCacheMetadata(),
            'data' => $data,
        ]);

        return $data;
    }

    /**
     * Get the cache key from the inner loader.
     */
    public function getCacheKey(): ?string
    {
        return $this->loader->getCacheKey();
    }

    /**
     * Get cache metadata from the inner loader.
     *
     * @return mixed
     */
    public function getCacheMetadata()
    {
        return $this->loader->getCacheMetadata();
    }

    /**
     * Check if cached data is still valid via the inner loader.
     *
     * @param mixed $metadata
     */
    public function isCacheValid($metadata): bool
    {
        return $this->loader->isCacheValid($metadata);
    }
}
