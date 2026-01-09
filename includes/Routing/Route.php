<?php

namespace Recca0120\ReverseProxy\Routing;

use Psr\Http\Message\ServerRequestInterface;
use Recca0120\ReverseProxy\Contracts\RouteAwareInterface;
use Recca0120\ReverseProxy\Support\Arr;
use Recca0120\ReverseProxy\Support\Str;

class Route
{
    /** @var string[] */
    private $methods = [];

    /** @var string */
    private $path;

    /** @var string */
    private $target;

    /** @var array */
    private $middlewares = [];

    /** @var array */
    private $captures = [];

    public function __construct(string $source, string $target, array $middlewares = [])
    {
        $this->parseSource($source);
        $this->target = $target;

        foreach ($middlewares as $middleware) {
            $this->middleware($middleware);
        }
    }

    /**
     * @param  callable|MiddlewareInterface  $middleware
     */
    public function middleware($middleware): self
    {
        if ($middleware instanceof RouteAwareInterface) {
            $middleware->setRoute($this);
        }

        $this->middlewares[] = $middleware;

        return $this;
    }

    public function getMiddlewares(): array
    {
        return $this->sortByPriority($this->middlewares);
    }

    public function matches(ServerRequestInterface $request): ?string
    {
        $uri = $request->getUri();
        $path = $uri->getPath() ?: '/';
        $queryString = $uri->getQuery() ?: '';

        if (! $this->matchesMethod($request->getMethod())) {
            return null;
        }

        if (! $this->matchesPattern($path)) {
            return null;
        }

        return $this->buildTargetUrl($path, $queryString);
    }

    public function getTargetHost(): string
    {
        return parse_url($this->target, PHP_URL_HOST) ?: '';
    }

    public function getCaptures(): array
    {
        return $this->captures;
    }

    private function parseSource(string $source): void
    {
        // Check if source contains HTTP method (e.g., "POST /api/users" or "GET|POST /api/*")
        if (preg_match('/^([A-Za-z|]+)\s+(\/.*)$/', $source, $matches)) {
            $this->methods = array_map('strtoupper', explode('|', $matches[1]));
            $this->path = $matches[2];
        } else {
            $this->methods = [];
            $this->path = $source;
        }
    }

    private function sortByPriority(array $middlewares): array
    {
        usort($middlewares, function ($a, $b) {
            $priorityA = $this->getPriority($a);
            $priorityB = $this->getPriority($b);

            return $priorityA <=> $priorityB;
        });

        return $middlewares;
    }

    /**
     * @param  mixed  $middleware
     */
    private function getPriority($middleware): int
    {
        if (is_object($middleware) && property_exists($middleware, 'priority')) {
            return (int) $middleware->priority;
        }

        return 0;
    }

    private function matchesMethod(string $method): bool
    {
        // Empty methods array means match all methods
        if (empty($this->methods)) {
            return true;
        }

        return Arr::contains($this->methods, strtoupper($method));
    }

    private function matchesPattern(string $path): bool
    {
        $regex = '#^'.str_replace('\*', '(.*)', preg_quote($this->path, '#')).'$#';

        if (preg_match($regex, $path, $matches)) {
            $this->captures = array_slice($matches, 1);

            return true;
        }

        return false;
    }

    private function buildTargetUrl(string $path, string $queryString): string
    {
        if ($this->hasTrailingSlash()) {
            $path = $this->stripBasePath($path);
        }

        $url = rtrim($this->target, '/').$path;

        if ($queryString !== '') {
            $url .= '?'.$queryString;
        }

        return $url;
    }

    /**
     * Check if target URL has a trailing slash (nginx-style prefix stripping).
     */
    private function hasTrailingSlash(): bool
    {
        return Str::endsWith($this->target, '/');
    }

    /**
     * Strip the base path prefix from the request path.
     */
    private function stripBasePath(string $path): string
    {
        $basePath = $this->getBasePath();

        if ($basePath !== '' && Str::startsWith($path, $basePath)) {
            $path = Str::after($path, $basePath);
        }

        return $path ?: '/';
    }

    /**
     * Get the base path (pattern without wildcard).
     */
    private function getBasePath(): string
    {
        return rtrim(preg_replace('/\*+$/', '', $this->path), '/');
    }
}
