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
    /** @var array<string, class-string<MiddlewareInterface>> */
    private $aliases = [
        'ProxyHeaders' => ProxyHeaders::class,
        'SetHost' => SetHost::class,
        'RewritePath' => RewritePath::class,
        'RewriteBody' => RewriteBody::class,
        'AllowMethods' => AllowMethods::class,
        'Cors' => Cors::class,
        'IpFilter' => IpFilter::class,
        'RateLimiting' => RateLimiting::class,
        'Caching' => Caching::class,
        'RequestId' => RequestId::class,
        'Retry' => Retry::class,
        'CircuitBreaker' => CircuitBreaker::class,
        'Timeout' => Timeout::class,
        'Fallback' => Fallback::class,
        'Logging' => Logging::class,
        'ErrorHandling' => ErrorHandling::class,
        'SanitizeHeaders' => SanitizeHeaders::class,
    ];

    /**
     * Create a middleware instance from configuration.
     *
     * @param  array{name: string, args?: array, options?: mixed}  $config
     */
    public function create(array $config): MiddlewareInterface
    {
        $name = $config['name'];
        $class = $this->aliases[$name] ?? $name;

        if (! class_exists($class)) {
            throw new InvalidArgumentException("Unknown middleware: {$name}");
        }

        $args = $this->resolveArguments($config);

        return new $class(...$args);
    }

    /**
     * Register a custom alias.
     *
     * @param  class-string<MiddlewareInterface>  $class
     */
    public function registerAlias(string $alias, string $class): void
    {
        $this->aliases[$alias] = $class;
    }

    /**
     * Get all registered aliases.
     *
     * @return array<string, class-string<MiddlewareInterface>>
     */
    public function getAliases(): array
    {
        return $this->aliases;
    }

    /**
     * Resolve arguments from config.
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
            $options = $config['options'];

            return is_array($options) ? [$options] : [$options];
        }

        return [];
    }
}
