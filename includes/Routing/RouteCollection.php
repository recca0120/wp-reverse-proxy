<?php

namespace Recca0120\ReverseProxy\Routing;

use ArrayAccess;
use ArrayIterator;
use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use Psr\SimpleCache\CacheInterface;
use Recca0120\ReverseProxy\Contracts\RouteLoaderInterface;
use Traversable;

class RouteCollection implements IteratorAggregate, Countable, ArrayAccess
{
    /** @var array<RouteLoaderInterface> */
    private $loaders;

    /** @var MiddlewareFactory */
    private $middlewareFactory;

    /** @var CacheInterface|null */
    private $cache;

    /** @var array<Route> */
    private $routes = [];

    /**
     * @param array<RouteLoaderInterface> $loaders
     */
    public function __construct(
        array $loaders = [],
        ?MiddlewareFactory $middlewareFactory = null,
        ?CacheInterface $cache = null
    ) {
        $this->loaders = $loaders;
        $this->middlewareFactory = $middlewareFactory ?? new MiddlewareFactory();
        $this->cache = $cache;
    }

    /**
     * Load routes from all configured loaders.
     */
    public function load(): self
    {
        $configs = $this->remember('route_configs', function () {
            $configs = [];
            foreach ($this->loaders as $loader) {
                foreach ($loader->load() as $config) {
                    $configs[] = $config;
                }
            }

            return $configs;
        });

        foreach ($configs as $config) {
            try {
                $this->routes[] = $this->createRoute($config);
            } catch (InvalidArgumentException $e) {
                // Skip invalid routes
                continue;
            }
        }

        return $this;
    }

    /**
     * Add route(s) to the collection.
     *
     * @param Route|array<Route> $routes
     */
    public function add($routes): self
    {
        if (is_array($routes)) {
            foreach ($routes as $route) {
                $this->routes[] = $route;
            }
        } else {
            $this->routes[] = $routes;
        }

        return $this;
    }

    /**
     * Get all routes.
     *
     * @return array<Route>
     */
    public function all(): array
    {
        return $this->routes;
    }

    /**
     * Get iterator for routes.
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->routes);
    }

    /**
     * Count routes.
     */
    public function count(): int
    {
        return count($this->routes);
    }

    /**
     * Check if offset exists.
     *
     * @param mixed $offset
     */
    public function offsetExists($offset): bool
    {
        return isset($this->routes[$offset]);
    }

    /**
     * Get route at offset.
     *
     * @param mixed $offset
     * @return Route|null
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->routes[$offset] ?? null;
    }

    /**
     * Set route at offset.
     *
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value): void
    {
        if ($offset === null) {
            $this->routes[] = $value;
        } else {
            $this->routes[$offset] = $value;
        }
    }

    /**
     * Unset route at offset.
     *
     * @param mixed $offset
     */
    public function offsetUnset($offset): void
    {
        unset($this->routes[$offset]);
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
     * Create a single Route from configuration.
     *
     * @param array<string, mixed> $config
     */
    private function createRoute(array $config): Route
    {
        $this->validateRoute($config);

        $path = $this->buildPath($config);
        $target = $config['target'];
        $middlewares = $this->middlewareFactory->createMany($config['middlewares'] ?? []);

        return new Route($path, $target, $middlewares);
    }

    /**
     * Build path string with optional methods.
     *
     * @param array<string, mixed> $config
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
     * Validate route configuration.
     *
     * @param array<string, mixed> $config
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

        if (!$this->isValidUrl($config['target'])) {
            throw new InvalidArgumentException('Invalid target URL: ' . $config['target']);
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
     * Get value from cache or execute callback and store result.
     *
     * @template T
     *
     * @param callable(): T $callback
     * @return T
     */
    private function remember(string $key, callable $callback)
    {
        if ($this->cache === null) {
            return $callback();
        }

        $cached = $this->cache->get($key);
        if ($cached !== null) {
            return $cached;
        }

        $data = $callback();
        $this->cache->set($key, $data);

        return $data;
    }
}
