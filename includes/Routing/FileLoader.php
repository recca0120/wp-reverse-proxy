<?php

namespace Recca0120\ReverseProxy\Routing;

use InvalidArgumentException;
use Psr\SimpleCache\CacheInterface;
use Recca0120\ReverseProxy\Contracts\FileLoaderInterface;
use Recca0120\ReverseProxy\Contracts\RouteLoaderInterface;
use Recca0120\ReverseProxy\Routing\Loaders\JsonLoader;
use Recca0120\ReverseProxy\Routing\Loaders\PhpArrayLoader;
use Recca0120\ReverseProxy\Routing\Loaders\YamlLoader;

class FileLoader implements RouteLoaderInterface
{
    /** @var array<string> */
    private $paths;

    /** @var array<FileLoaderInterface> */
    private $parsers;

    /** @var MiddlewareFactory */
    private $middlewareFactory;

    /** @var CacheInterface|null */
    private $cache;

    /** @var string */
    private $pattern;

    /**
     * @param array<string> $paths Directories or files to load routes from
     * @param array<FileLoaderInterface>|null $parsers Custom parsers (default: JSON, YAML, PHP)
     */
    public function __construct(
        array $paths,
        ?array $parsers = null,
        ?MiddlewareFactory $middlewareFactory = null,
        ?CacheInterface $cache = null,
        string $pattern = '*.{json,yaml,yml,php}'
    ) {
        $this->paths = $paths;
        $this->parsers = $parsers ?? $this->defaultParsers();
        $this->middlewareFactory = $middlewareFactory ?? new MiddlewareFactory();
        $this->cache = $cache;
        $this->pattern = $pattern;
    }

    /**
     * Load routes from all configured paths.
     *
     * @return array<Route>
     */
    public function load(): array
    {
        $routes = [];

        foreach ($this->paths as $path) {
            $routes = array_merge($routes, $this->loadFromPath($path));
        }

        return $routes;
    }

    /**
     * @return array<Route>
     */
    private function loadFromPath(string $path): array
    {
        if (!file_exists($path)) {
            return [];
        }

        if (is_dir($path)) {
            return $this->loadFromDirectory($path);
        }

        return $this->loadFromFile($path);
    }

    /**
     * @return array<Route>
     */
    private function loadFromDirectory(string $directory): array
    {
        $files = $this->globFiles($directory, $this->pattern);
        $routes = [];

        foreach ($files as $file) {
            $routes = array_merge($routes, $this->loadFromFile($file));
        }

        return $routes;
    }

    /**
     * @return array<Route>
     */
    private function loadFromFile(string $file): array
    {
        return $this->remember($file, function () use ($file) {
            $config = $this->parseFile($file);

            if (empty($config) || !isset($config['routes'])) {
                return [];
            }

            return $this->createRoutes($config['routes']);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function parseFile(string $file): array
    {
        foreach ($this->parsers as $parser) {
            if ($parser->supports($file)) {
                return $parser->load($file);
            }
        }

        return [];
    }

    /**
     * @param array<array<string, mixed>> $configs
     * @return array<Route>
     */
    private function createRoutes(array $configs): array
    {
        $routes = [];

        foreach ($configs as $config) {
            try {
                $routes[] = $this->createRoute($config);
            } catch (InvalidArgumentException $e) {
                // Skip invalid routes
                continue;
            }
        }

        return $routes;
    }

    /**
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
     * @param array<string, mixed> $config
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

    private function isValidUrl(string $url): bool
    {
        $parsed = parse_url($url);

        return $parsed !== false
            && isset($parsed['scheme'])
            && isset($parsed['host'])
            && in_array($parsed['scheme'], ['http', 'https'], true);
    }

    /**
     * @return array<string>
     */
    private function globFiles(string $directory, string $pattern): array
    {
        $fullPattern = $directory . '/' . $pattern;

        if (defined('GLOB_BRACE') && strpos($pattern, '{') !== false) {
            return $this->safeGlob($fullPattern, GLOB_BRACE);
        }

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
     * @return array<string>
     */
    private function safeGlob(string $pattern, int $flags = 0): array
    {
        return glob($pattern, $flags) ?: [];
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    private function remember(string $file, callable $callback)
    {
        if ($this->cache === null) {
            return $callback();
        }

        $key = 'route_file_' . md5($file);
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
     * @return array<FileLoaderInterface>
     */
    private function defaultParsers(): array
    {
        return [
            new JsonLoader(),
            new YamlLoader(),
            new PhpArrayLoader(),
        ];
    }
}
