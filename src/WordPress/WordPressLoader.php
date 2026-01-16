<?php

namespace Recca0120\ReverseProxy\WordPress;

use Recca0120\ReverseProxy\Contracts\RouteLoaderInterface;
use Recca0120\ReverseProxy\Contracts\StorageInterface;
use Recca0120\ReverseProxy\WordPress\Admin\OptionsStorage;

class WordPressLoader implements RouteLoaderInterface
{
    /** @var StorageInterface */
    private $storage;

    public function __construct(?StorageInterface $storage = null)
    {
        $this->storage = $storage ?? new OptionsStorage();
    }

    /**
     * Get a stable identifier based on storage class.
     */
    public function getIdentifier(): string
    {
        return md5(get_class($this->storage));
    }

    /**
     * Load route configurations from storage.
     *
     * @return array<array<string, mixed>>
     */
    public function load(): array
    {
        $routes = $this->storage->all();

        if (empty($routes) || !is_array($routes)) {
            return [];
        }

        $result = [];

        foreach ($routes as $route) {
            if (empty($route['enabled'])) {
                continue;
            }

            $result[] = $this->formatRoute($route);
        }

        return $result;
    }

    /**
     * Get a fingerprint for cache identification and validation.
     *
     * Returns a string combining the storage class and version number.
     */
    public function getFingerprint(): ?string
    {
        return get_class($this->storage) . ':' . $this->storage->getVersion();
    }

    /**
     * Format route data to match the expected format.
     *
     * @param array<string, mixed> $route
     * @return array<string, mixed>
     */
    private function formatRoute(array $route): array
    {
        $path = $route['path'] ?? '';
        $methods = $route['methods'] ?? [];

        // Prepend methods to path if specified
        if (!empty($methods)) {
            $path = implode('|', $methods) . ' ' . $path;
        }

        return [
            'path' => $path,
            'target' => $route['target'] ?? '',
            'middlewares' => $route['middlewares'] ?? [],
        ];
    }
}
