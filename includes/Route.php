<?php

namespace ReverseProxy;

use Psr\Http\Message\ServerRequestInterface;

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

    public function __construct(string $source, string $target, array $middlewares = [])
    {
        $this->parseSource($source);
        $this->target = $target;

        foreach ($middlewares as $middleware) {
            $this->middleware($middleware);
        }
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

    /**
     * @param  callable|MiddlewareInterface  $middleware
     */
    public function middleware($middleware): self
    {
        $this->middlewares[] = $middleware;

        return $this;
    }

    public function getMiddlewares(): array
    {
        return $this->sortByPriority($this->middlewares);
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

    private function matchesMethod(string $method): bool
    {
        // Empty methods array means match all methods
        if (empty($this->methods)) {
            return true;
        }

        return in_array(strtoupper($method), $this->methods, true);
    }

    public function getTargetHost(): string
    {
        return parse_url($this->target, PHP_URL_HOST) ?: '';
    }

    private function matchesPattern(string $path): bool
    {
        $regex = '#^'.str_replace('\*', '(.*)', preg_quote($this->path, '#')).'$#';

        return (bool) preg_match($regex, $path);
    }

    private function buildTargetUrl(string $path, string $queryString): string
    {
        $url = rtrim($this->target, '/').$path;

        if ($queryString !== '') {
            $url .= '?'.$queryString;
        }

        return $url;
    }
}
