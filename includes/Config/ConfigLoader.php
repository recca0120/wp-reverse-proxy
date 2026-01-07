<?php

namespace Recca0120\ReverseProxy\Config;

use InvalidArgumentException;
use Psr\SimpleCache\CacheInterface;
use Recca0120\ReverseProxy\Contracts\ConfigLoaderInterface;
use Recca0120\ReverseProxy\Route;

class ConfigLoader
{
    /** @var array<ConfigLoaderInterface> */
    private $loaders;

    /** @var MiddlewareFactory */
    private $middlewareFactory;

    /** @var CacheInterface|null */
    private $cache;

    /**
     * @param  array<ConfigLoaderInterface>  $loaders
     */
    public function __construct(
        array $loaders,
        MiddlewareFactory $middlewareFactory,
        ?CacheInterface $cache = null
    ) {
        $this->loaders = $loaders;
        $this->middlewareFactory = $middlewareFactory;
        $this->cache = $cache;
    }

    /**
     * Load routes from a directory.
     *
     * @return array<Route>
     */
    public function loadFromDirectory(string $directory, string $pattern = '*.routes.*'): array
    {
        $files = is_dir($directory) ? $this->globFiles($directory, $pattern) : [];

        $routes = [];
        foreach ($files as $file) {
            $routes = array_merge($routes, $this->loadFromFile($file));
        }

        return $routes;
    }

    /**
     * Get files matching pattern with brace expansion support.
     *
     * @return array<string>
     */
    private function globFiles(string $directory, string $pattern): array
    {
        $fullPattern = $directory.'/'.$pattern;

        // Try GLOB_BRACE if available and pattern uses brace syntax
        if (defined('GLOB_BRACE') && strpos($pattern, '{') !== false) {
            return $this->safeGlob($fullPattern, GLOB_BRACE);
        }

        // Manual brace expansion fallback
        if (preg_match('/\{([^}]+)\}/', $pattern, $matches)) {
            $files = [];
            foreach (explode(',', $matches[1]) as $alt) {
                $files = array_merge($files, $this->safeGlob(str_replace($matches[0], $alt, $fullPattern)));
            }

            return array_unique($files);
        }

        return $this->safeGlob($fullPattern);
    }

    /**
     * Safe glob wrapper that always returns an array.
     *
     * @return array<string>
     */
    private function safeGlob(string $pattern, int $flags = 0): array
    {
        return glob($pattern, $flags) ?: [];
    }

    /**
     * Load routes from a single file.
     *
     * @return array<Route>
     */
    public function loadFromFile(string $file): array
    {
        return $this->remember($file, function () use ($file) {
            $config = $this->loadConfig($file);
            if (empty($config) || ! isset($config['routes'])) {
                return [];
            }

            return $this->createRoutes($config['routes']);
        });
    }

    /**
     * Get value from cache or execute callback and store result.
     * Uses file modification time for cache invalidation.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function remember(string $file, callable $callback)
    {
        if ($this->cache === null) {
            return $callback();
        }

        $key = $this->getCacheKey($file);
        $mtime = file_exists($file) ? filemtime($file) : 0;

        $cached = $this->cache->get($key);
        if ($cached !== null && isset($cached['mtime']) && $cached['mtime'] === $mtime) {
            return $cached['data'];
        }

        $data = $callback();
        $this->cache->set($key, ['mtime' => $mtime, 'data' => $data]);

        return $data;
    }

    /**
     * Clear all cached routes.
     */
    public function clearCache(): void
    {
        if ($this->cache === null) {
            return;
        }

        $this->cache->clear();
    }

    /**
     * Load configuration from file using appropriate loader.
     *
     * @return array<string, mixed>
     */
    private function loadConfig(string $file): array
    {
        foreach ($this->loaders as $loader) {
            if ($loader->supports($file)) {
                return $loader->load($file);
            }
        }

        return [];
    }

    /**
     * Create Route objects from configuration.
     *
     * @param  array<array<string, mixed>>  $routeConfigs
     * @return array<Route>
     */
    private function createRoutes(array $routeConfigs): array
    {
        return array_map([$this, 'createRoute'], $routeConfigs);
    }

    /**
     * Create a single Route from configuration.
     *
     * @param  array<string, mixed>  $config
     */
    private function createRoute(array $config): Route
    {
        $this->validateRoute($config);

        $path = $this->buildPath($config);
        $target = $config['target'];
        $middlewares = $this->createMiddlewares($config['middlewares'] ?? []);

        return new Route($path, $target, $middlewares);
    }

    /**
     * Build path string with optional methods.
     *
     * @param  array<string, mixed>  $config
     */
    private function buildPath(array $config): string
    {
        if (empty($config['methods'])) {
            return $config['path'];
        }

        $methods = implode('|', array_map('strtoupper', $config['methods']));

        return "{$methods} {$config['path']}";
    }

    /**
     * Create middleware instances from configuration.
     *
     * @param  string|array  $middlewareConfigs
     * @return array<mixed>
     */
    private function createMiddlewares($middlewareConfigs): array
    {
        return $this->middlewareFactory->createMany($middlewareConfigs);
    }

    /**
     * Validate route configuration.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    private function validateRoute(array $config): void
    {
        if (empty($config['path'])) {
            throw new InvalidArgumentException('Route configuration must have a "path" field');
        }

        if (empty($config['target'])) {
            throw new InvalidArgumentException('Route configuration must have a "target" field');
        }

        if (! $this->isValidUrl($config['target'])) {
            throw new InvalidArgumentException('Invalid target URL: '.$config['target']);
        }
    }

    /**
     * Check if a string is a valid URL.
     */
    private function isValidUrl(string $url): bool
    {
        $parsed = parse_url($url);

        return $parsed !== false
            && isset($parsed['scheme'])
            && isset($parsed['host'])
            && in_array($parsed['scheme'], ['http', 'https'], true);
    }

    /**
     * Generate cache key for a file.
     */
    private function getCacheKey(string $file): string
    {
        return 'route_config_'.md5($file);
    }
}
