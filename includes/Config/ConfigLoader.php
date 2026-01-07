<?php

namespace Recca0120\ReverseProxy\Config;

use InvalidArgumentException;
use Psr\SimpleCache\CacheInterface;
use Recca0120\ReverseProxy\Config\Contracts\LoaderInterface;
use Recca0120\ReverseProxy\Route;

class ConfigLoader
{
    /** @var array<LoaderInterface> */
    private $loaders;

    /** @var MiddlewareFactory */
    private $middlewareFactory;

    /** @var CacheInterface|null */
    private $cache;

    /** @var int */
    private $cacheTtl;

    /**
     * @param  array<LoaderInterface>  $loaders
     */
    public function __construct(
        array $loaders,
        MiddlewareFactory $middlewareFactory,
        ?CacheInterface $cache = null,
        int $cacheTtl = 3600
    ) {
        $this->loaders = $loaders;
        $this->middlewareFactory = $middlewareFactory;
        $this->cache = $cache;
        $this->cacheTtl = $cacheTtl;
    }

    /**
     * Load routes from a directory.
     *
     * @return array<Route>
     */
    public function loadFromDirectory(string $directory, string $pattern = '*.routes.*'): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $files = glob($directory.'/'.$pattern);
        if ($files === false || empty($files)) {
            return [];
        }

        $routes = [];
        foreach ($files as $file) {
            $routes = array_merge($routes, $this->loadFromFile($file));
        }

        return $routes;
    }

    /**
     * Load routes from a single file.
     *
     * @return array<Route>
     */
    public function loadFromFile(string $file): array
    {
        return $this->remember($this->getCacheKey($file), function () use ($file) {
            $config = $this->loadConfig($file);
            if (empty($config) || ! isset($config['routes'])) {
                return [];
            }

            return $this->createRoutes($config['routes']);
        });
    }

    /**
     * Get value from cache or execute callback and store result.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function remember(string $key, callable $callback)
    {
        if ($this->cache !== null) {
            $cached = $this->cache->get($key);
            if ($cached !== null) {
                return $cached;
            }
        }

        $value = $callback();

        if ($this->cache !== null) {
            $this->cache->set($key, $value, $this->cacheTtl);
        }

        return $value;
    }

    /**
     * Clear all cached routes.
     */
    public function clearCache(): void
    {
        if ($this->cache !== null) {
            $this->cache->clear();
        }
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
        $routes = [];

        foreach ($routeConfigs as $config) {
            $routes[] = $this->createRoute($config);
        }

        return $routes;
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
        $path = $config['path'];

        if (isset($config['methods']) && is_array($config['methods'])) {
            $methods = implode('|', array_map('strtoupper', $config['methods']));

            return "{$methods} {$path}";
        }

        return $path;
    }

    /**
     * Create middleware instances from configuration.
     *
     * @param  array<array<string, mixed>>  $middlewareConfigs
     * @return array<mixed>
     */
    private function createMiddlewares(array $middlewareConfigs): array
    {
        $middlewares = [];

        foreach ($middlewareConfigs as $config) {
            $middlewares[] = $this->middlewareFactory->create($config);
        }

        return $middlewares;
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
        if (! isset($config['path']) || empty($config['path'])) {
            throw new InvalidArgumentException('Route configuration must have a "path" field');
        }

        if (! isset($config['target']) || empty($config['target'])) {
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
        $mtime = file_exists($file) ? filemtime($file) : 0;

        return 'route_config_'.md5($file.'_'.$mtime);
    }
}
