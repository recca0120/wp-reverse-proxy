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
     * Get a stable identifier for cache key generation.
     *
     * This should return a consistent value that uniquely identifies this loader
     * instance (e.g., md5 of configured paths or storage class name).
     * Unlike fingerprint, this value should NOT change when source data changes.
     */
    public function getIdentifier(): string;

    /**
     * Get a fingerprint for cache validation.
     *
     * - Return null to disable caching for this loader
     * - Return a unique string that changes when the source data changes
     *   (e.g., file paths + mtimes, version hash, etc.)
     */
    public function getFingerprint(): ?string;
}
