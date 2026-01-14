<?php

namespace Recca0120\ReverseProxy\Routing;

use Psr\SimpleCache\CacheInterface;
use Recca0120\ReverseProxy\Contracts\RouteLoaderInterface;

class CachedRouteLoader implements RouteLoaderInterface
{
    private const CACHE_KEY_PREFIX = 'route_loader_';

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
        $fingerprint = $this->loader->getFingerprint();

        if ($fingerprint === null) {
            return $this->loader->load();
        }

        $cacheKey = $this->getCacheKey();
        $cached = $this->cache->get($cacheKey);

        if ($cached !== null && $cached['fingerprint'] === $fingerprint) {
            return $cached['data'];
        }

        $data = $this->loader->load();
        $this->cache->set($cacheKey, [
            'fingerprint' => $fingerprint,
            'data' => $data,
        ]);

        return $data;
    }

    /**
     * Get the identifier from the inner loader.
     */
    public function getIdentifier(): string
    {
        return $this->loader->getIdentifier();
    }

    /**
     * Get the fingerprint from the inner loader.
     */
    public function getFingerprint(): ?string
    {
        return $this->loader->getFingerprint();
    }

    /**
     * Clear the cache for this loader.
     */
    public function clearCache(): void
    {
        $this->cache->delete($this->getCacheKey());
    }

    /**
     * Get the cache key for this loader.
     */
    private function getCacheKey(): string
    {
        return self::CACHE_KEY_PREFIX . $this->loader->getIdentifier();
    }
}
