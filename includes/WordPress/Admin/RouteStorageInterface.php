<?php

namespace Recca0120\ReverseProxy\WordPress\Admin;

interface RouteStorageInterface
{
    /**
     * Get all routes.
     *
     * @return array<int, array>
     */
    public function getAll(): array;

    /**
     * Save all routes.
     *
     * @param array<int, array> $routes
     * @return bool
     */
    public function save(array $routes): bool;

    /**
     * Get storage version for cache invalidation.
     *
     * @return int
     */
    public function getVersion(): int;
}
