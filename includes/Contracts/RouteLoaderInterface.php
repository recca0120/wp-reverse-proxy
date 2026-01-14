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
     * Get a fingerprint for cache identification and validation.
     *
     * - Return null to disable caching for this loader
     * - Return a unique string that changes when the source data changes
     *   (e.g., file paths + mtimes, version hash, etc.)
     */
    public function getFingerprint(): ?string;
}
