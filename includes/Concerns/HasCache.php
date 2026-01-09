<?php

namespace Recca0120\ReverseProxy\Concerns;

use Psr\SimpleCache\CacheInterface;
use Recca0120\ReverseProxy\WordPress\TransientCache;

trait HasCache
{
    /** @var CacheInterface|null */
    private $cache;

    public function setCache(CacheInterface $cache): void
    {
        $this->cache = $cache;
    }

    protected function cacheGet(string $key, $default = null)
    {
        return $this->getCache()->get($this->getCachePrefix() . $key, $default);
    }

    protected function cacheSet(string $key, $value, $ttl = null): bool
    {
        return $this->getCache()->set($this->getCachePrefix() . $key, $value, $ttl);
    }

    protected function getCachePrefix(): string
    {
        return 'rp_';
    }

    private function getCache(): CacheInterface
    {
        if ($this->cache === null) {
            $this->cache = new TransientCache();
        }

        return $this->cache;
    }
}
