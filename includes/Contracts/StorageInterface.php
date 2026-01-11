<?php

namespace Recca0120\ReverseProxy\Contracts;

interface StorageInterface
{
    /**
     * Get all routes.
     *
     * @return array<int, array>
     */
    public function all(): array;

    /**
     * Find a route by ID.
     *
     * @param string $id
     * @return array|null
     */
    public function find(string $id): ?array;

    /**
     * Save all routes.
     *
     * @param array<int, array> $routes
     * @return bool
     */
    public function save(array $routes): bool;

    /**
     * Update a route by ID.
     *
     * @param string $id
     * @param array $route
     * @return bool
     */
    public function update(string $id, array $route): bool;

    /**
     * Delete a route by ID.
     *
     * @param string $id
     * @return bool
     */
    public function delete(string $id): bool;

    /**
     * Get storage version for cache invalidation.
     *
     * @return int
     */
    public function getVersion(): int;
}
