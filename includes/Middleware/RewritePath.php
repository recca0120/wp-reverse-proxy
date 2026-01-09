<?php

namespace Recca0120\ReverseProxy\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Recca0120\ReverseProxy\Contracts\MiddlewareInterface;
use Recca0120\ReverseProxy\Routing\Route;

class RewritePath implements MiddlewareInterface
{
    /** @var string */
    private $replacement;

    /** @var Route|null */
    private $route;

    public function __construct(string $replacement)
    {
        $this->replacement = $replacement;
    }

    /**
     * Set the route for capture access (used by Route::middleware()).
     */
    public function setRoute(Route $route): void
    {
        $this->route = $route;
    }

    public function process(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        $captures = $request->getAttribute('route_captures')
            ?? ($this->route ? $this->route->getCaptures() : []);
        $newPath = $this->applyReplacement($captures);

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
