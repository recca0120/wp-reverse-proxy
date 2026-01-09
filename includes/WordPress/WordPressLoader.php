<?php

namespace Recca0120\ReverseProxy\WordPress;

use Recca0120\ReverseProxy\Contracts\RouteLoaderInterface;
use Recca0120\ReverseProxy\WordPress\Admin\RoutesPage;

class WordPressLoader implements RouteLoaderInterface
{
    /** @var string */
    private $optionName;

    public function __construct(?string $optionName = null)
    {
        $this->optionName = $optionName ?? RoutesPage::OPTION_NAME;
    }

    /**
     * Load route configurations from wp_options.
     *
     * @return array<array<string, mixed>>
     */
    public function load(): array
    {
        $routes = get_option($this->optionName, []);

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
     * Get the cache key for this loader.
     */
    public function getCacheKey(): ?string
    {
        return 'wordpress_loader_' . md5($this->optionName);
    }

    /**
     * Get metadata for cache validation (version number).
     *
     * @return int
     */
    public function getCacheMetadata()
    {
        return RoutesPage::getVersion();
    }

    /**
     * Check if cached data is still valid by comparing version.
     *
     * @param mixed $metadata The version stored with cached data
     */
    public function isCacheValid($metadata): bool
    {
        return (int) $metadata === $this->getCacheMetadata();
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
