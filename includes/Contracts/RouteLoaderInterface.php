<?php

namespace Recca0120\ReverseProxy\Contracts;

interface RouteLoaderInterface
{
    /**
     * Load route configurations from the source.
     *
     * @return array<array<string, mixed>> Array of route config arrays
     */
    public function load(): array;

    /**
     * Get the cache key for this loader.
     * Return null to disable caching for this loader.
     */
    public function getCacheKey(): ?string;

    /**
     * Get metadata for cache validation (e.g., mtime, version).
     *
     * @return mixed
     */
    public function getCacheMetadata();

    /**
     * Check if cached data is still valid.
     *
     * @param mixed $metadata The metadata stored with cached data
     */
    public function isCacheValid($metadata): bool;
}
