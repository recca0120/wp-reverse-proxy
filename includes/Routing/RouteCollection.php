<?php

namespace Recca0120\ReverseProxy\Routing;

use ArrayAccess;
use ArrayIterator;
use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use Psr\SimpleCache\CacheInterface;
use Recca0120\ReverseProxy\Contracts\CacheAwareInterface;
use Recca0120\ReverseProxy\Contracts\RouteLoaderInterface;
use Recca0120\ReverseProxy\Support\Arr;
use Traversable;

class RouteCollection implements IteratorAggregate, Countable, ArrayAccess
{
    /** @var array<RouteLoaderInterface> */
    private $loaders;

    /** @var MiddlewareManager */
    private $middlewareManager;

    /** @var CacheInterface|null */
    private $cache;

    /** @var array<Route> */
    private $routes = [];

    /** @var bool */
    private $loaded = false;

    /**
     * @param array<RouteLoaderInterface> $loaders
     */
    public function __construct(
        array $loaders = [],
        ?CacheInterface $cache = null,
        ?MiddlewareManager $middlewareManager = null
    ) {
        $this->loaders = $this->wrapLoadersWithCache($loaders, $cache);
        $this->cache = $cache;
        $this->middlewareManager = $middlewareManager ?? new MiddlewareManager();
    }

    /**
     * Get the middleware manager.
     */
    public function getMiddlewareManager(): MiddlewareManager
    {
        return $this->middlewareManager;
    }

    /**
     * Load routes from all configured loaders.
     */
    public function load(): self
    {
        if ($this->loaded) {
            return $this;
        }

        foreach ($this->loaders as $loader) {
            foreach ($loader->load() as $config) {
                try {
                    $this->addRoute($this->createRoute($config));
                } catch (InvalidArgumentException $e) {
                    // Skip invalid routes
                    continue;
                }
            }
        }

        $this->loaded = true;

        return $this;
    }

    /**
     * Add route(s) to the collection.
     *
     * @param Route|array<Route> $routes
     */
    public function add($routes): self
    {
        $routes = is_array($routes) ? $routes : [$routes];

        foreach ($routes as $route) {
            $this->addRoute($route);
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
        $this->ensureLoaded();

        return $this->routes;
    }

    /**
     * Get iterator for routes.
     */
    public function getIterator(): Traversable
    {
        $this->ensureLoaded();

        return new ArrayIterator($this->routes);
    }

    /**
     * Count routes.
     */
    public function count(): int
    {
        $this->ensureLoaded();

        return count($this->routes);
    }

    /**
     * Check if offset exists.
     *
     * @param mixed $offset
     */
    public function offsetExists($offset): bool
    {
        $this->ensureLoaded();

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
        $this->ensureLoaded();

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
        $this->addRoute($value, $offset);
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
     * Clear cached routes for all loaders and reset loaded state.
     */
    public function clearCache(): void
    {
        if ($this->cache !== null) {
            foreach ($this->loaders as $loader) {
                if ($loader instanceof CachedRouteLoader) {
                    $cacheKey = $loader->getCacheKey();
                    if ($cacheKey !== null) {
                        $this->cache->delete($cacheKey);
                    }
                }
            }
        }

        $this->routes = [];
        $this->loaded = false;
    }

    /**
     * Ensure routes are loaded before access.
     */
    private function ensureLoaded(): void
    {
        if (! $this->loaded) {
            $this->load();
        }
    }

    /**
     * Wrap loaders with cache decorator if cache is provided.
     *
     * @param array<RouteLoaderInterface> $loaders
     * @return array<RouteLoaderInterface>
     */
    private function wrapLoadersWithCache(array $loaders, ?CacheInterface $cache): array
    {
        if ($cache === null) {
            return $loaders;
        }

        return array_map(
            static function (RouteLoaderInterface $loader) use ($cache) {
                return new CachedRouteLoader($loader, $cache);
            },
            $loaders
        );
    }

    /**
     * Create a single Route from configuration.
     *
     * @param array<string, mixed> $config
     */
    private function createRoute(array $config): Route
    {
        $this->validateRoute($config);

        return new Route(
            $this->buildPath($config),
            $config['target'],
            $this->middlewareManager->createMany($config['middlewares'] ?? [])
        );
    }

    /**
     * Add a single route to the collection with cache injection.
     *
     * @param mixed $offset
     */
    private function addRoute(Route $route, $offset = null): void
    {
        if ($this->cache !== null) {
            foreach ($route->getMiddlewares() as $middleware) {
                if ($middleware instanceof CacheAwareInterface) {
                    $middleware->setCache($this->cache);
                }
            }
        }

        if ($offset === null) {
            $this->routes[] = $route;
        } else {
            $this->routes[$offset] = $route;
        }
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
            && Arr::has($parsed, 'scheme')
            && Arr::has($parsed, 'host')
            && Arr::contains(['http', 'https'], $parsed['scheme']);
    }

}
