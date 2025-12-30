<?php

namespace ReverseProxy\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use ReverseProxy\Contracts\MiddlewareInterface;
use ReverseProxy\Contracts\RouteAwareInterface;
use ReverseProxy\Route;

class RewritePath implements MiddlewareInterface, RouteAwareInterface
{
    /** @var string */
    private $replacement;

    /** @var Route */
    private $route;

    public function __construct(string $replacement)
    {
        $this->replacement = $replacement;
    }

    public function setRoute(Route $route): void
    {
        $this->route = $route;
    }

    public function process(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        $newPath = $this->applyReplacement($this->route->getCaptures());

        $uri = $request->getUri();
        if ($newPath !== $uri->getPath()) {
            $request = $request->withUri($uri->withPath($newPath), true);
        }

        return $next($request);
    }

    private function applyReplacement(array $captures): string
    {
        $result = $this->replacement;

        foreach ($captures as $i => $value) {
            $result = str_replace('$'.($i + 1), $value, $result);
        }

        return $result;
    }
}
