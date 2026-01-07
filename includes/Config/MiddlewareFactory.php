<?php

namespace Recca0120\ReverseProxy\Config;

use InvalidArgumentException;
use Recca0120\ReverseProxy\Contracts\MiddlewareInterface;
use Recca0120\ReverseProxy\Middleware\AllowMethods;
use Recca0120\ReverseProxy\Middleware\Caching;
use Recca0120\ReverseProxy\Middleware\CircuitBreaker;
use Recca0120\ReverseProxy\Middleware\Cors;
use Recca0120\ReverseProxy\Middleware\ErrorHandling;
use Recca0120\ReverseProxy\Middleware\Fallback;
use Recca0120\ReverseProxy\Middleware\IpFilter;
use Recca0120\ReverseProxy\Middleware\Logging;
use Recca0120\ReverseProxy\Middleware\ProxyHeaders;
use Recca0120\ReverseProxy\Middleware\RateLimiting;
use Recca0120\ReverseProxy\Middleware\RequestId;
use Recca0120\ReverseProxy\Middleware\Retry;
use Recca0120\ReverseProxy\Middleware\RewriteBody;
use Recca0120\ReverseProxy\Middleware\RewritePath;
use Recca0120\ReverseProxy\Middleware\SanitizeHeaders;
use Recca0120\ReverseProxy\Middleware\SetHost;
use Recca0120\ReverseProxy\Middleware\Timeout;

class MiddlewareFactory
{
    /** @var array<class-string<MiddlewareInterface>> */
    private static $defaultMiddlewares = [
        ProxyHeaders::class,
        SetHost::class,
        RewritePath::class,
        RewriteBody::class,
        AllowMethods::class,
        Cors::class,
        IpFilter::class,
        RateLimiting::class,
        Caching::class,
        RequestId::class,
        Retry::class,
        CircuitBreaker::class,
        Timeout::class,
        Fallback::class,
        Logging::class,
        ErrorHandling::class,
        SanitizeHeaders::class,
    ];

    /** @var array<string, class-string<MiddlewareInterface>>|null */
    private $aliases;

    /**
     * Create a middleware instance from configuration.
     *
     * Supported formats:
     * - String: "ProxyHeaders" or "SetHost:api.example.com"
     * - Array shorthand: ["SetHost", "api.example.com"]
     * - Full object: ["name" => "SetHost", "options" => "api.example.com"]
     *
     * @param  string|array  $config
     */
    public function create($config): MiddlewareInterface
    {
        $config = $this->normalizeConfig($config);
        $name = $config['name'];
        $aliases = $this->getAliases();
        $class = $aliases[$name] ?? $name;

        if (! class_exists($class)) {
            throw new InvalidArgumentException("Unknown middleware: {$name}");
        }

        $args = $this->resolveArguments($config);

        return new $class(...$args);
    }

    /**
     * Create multiple middleware instances.
     *
     * Supported formats:
     * - Pipe-separated string: "ProxyHeaders|SetHost:api.example.com|Timeout:30"
     * - Array of configs: ["ProxyHeaders", "SetHost:api.example.com", ["Timeout", 30]]
     *
     * @param  string|array  $configs
     * @return array<MiddlewareInterface>
     */
    public function createMany($configs): array
    {
        if (is_string($configs)) {
            $configs = explode('|', $configs);
        }

        return array_map([$this, 'create'], $configs);
    }

    /**
     * Normalize config to standard format.
     *
     * @param  string|array  $config
     * @return array{name: string, args?: array, options?: mixed}
     */
    private function normalizeConfig($config): array
    {
        // String format: "ProxyHeaders" or "SetHost:api.example.com"
        if (is_string($config)) {
            return $this->parseStringConfig($config);
        }

        // Already standard format: ["name" => "...", ...]
        if (isset($config['name'])) {
            return $config;
        }

        // Array shorthand: ["SetHost", "api.example.com"] or ["SetHost", ["option" => "value"]]
        $name = array_shift($config);
        $args = array_values($config);

        return empty($args) ? ['name' => $name] : ['name' => $name, 'args' => $args];
    }

    /**
     * Parse string config format.
     *
     * @return array{name: string, args?: array}
     */
    private function parseStringConfig(string $config): array
    {
        // No colon means no parameters
        if (strpos($config, ':') === false) {
            return ['name' => $config];
        }

        // Split by first colon: "SetHost:api.example.com" or "RateLimiting:100,60"
        [$name, $params] = explode(':', $config, 2);
        $args = array_map([$this, 'castValue'], explode(',', $params));

        return ['name' => $name, 'args' => $args];
    }

    /** @var array<string, mixed> */
    private static $castMap = [
        'true' => true,
        'false' => false,
        'null' => null,
    ];

    /**
     * Cast string value to appropriate type.
     *
     * @return mixed
     */
    private function castValue(string $value)
    {
        if (array_key_exists($value, self::$castMap)) {
            return self::$castMap[$value];
        }

        if (is_numeric($value)) {
            return strpos($value, '.') !== false ? (float) $value : (int) $value;
        }

        return $value;
    }

    /**
     * Register a custom alias.
     *
     * @param  class-string<MiddlewareInterface>  $class
     */
    public function registerAlias(string $alias, string $class): void
    {
        $this->getAliases();
        $this->aliases[$alias] = $class;
    }

    /**
     * Get all registered aliases.
     *
     * @return array<string, class-string<MiddlewareInterface>>
     */
    public function getAliases(): array
    {
        if ($this->aliases === null) {
            $this->aliases = $this->buildAliases(self::$defaultMiddlewares);
        }

        return $this->aliases;
    }

    /**
     * Build aliases from middleware classes.
     *
     * @param  array<class-string<MiddlewareInterface>>  $classes
     * @return array<string, class-string<MiddlewareInterface>>
     */
    private function buildAliases(array $classes): array
    {
        $aliases = [];
        foreach ($classes as $class) {
            $shortName = substr(strrchr($class, '\\'), 1) ?: $class;
            $aliases[$shortName] = $class;
        }

        return $aliases;
    }

    /**
     * Resolve arguments from config.
     *
     * args: positional arguments array, spread into constructor
     * options: single value or associative array, wrapped and passed to constructor
     *
     * @param  array{name: string, args?: array, options?: mixed}  $config
     * @return array<int, mixed>
     */
    private function resolveArguments(array $config): array
    {
        if (isset($config['args'])) {
            return (array) $config['args'];
        }

        if (isset($config['options'])) {
            return [$config['options']];
        }

        return [];
    }
}
