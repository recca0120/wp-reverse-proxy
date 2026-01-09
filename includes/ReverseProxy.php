<?php

namespace Recca0120\ReverseProxy;

use Nyholm\Psr7\Uri;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Recca0120\ReverseProxy\Contracts\MiddlewareInterface;

class ReverseProxy
{
    /** @var Route[] */
    private $routes;

    /** @var ClientInterface */
    private $client;

    /** @var RequestFactoryInterface */
    private $requestFactory;

    /** @var StreamFactoryInterface */
    private $streamFactory;

    /** @var array */
    private $globalMiddlewares = [];

    /**
     * @param  Route[]  $routes
     */
    public function __construct(
        array $routes,
        ClientInterface $client,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory
    ) {
        $this->routes = $routes;
        $this->client = $client;
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
    }

    /**
     * @param  callable|MiddlewareInterface  $middleware
     */
    public function addGlobalMiddleware($middleware): self
    {
        $this->globalMiddlewares[] = $middleware;

        return $this;
    }

    public function addGlobalMiddlewares(array $middlewares): self
    {
        $this->globalMiddlewares = array_merge($this->globalMiddlewares, $middlewares);

        return $this;
    }

    public function handle(ServerRequestInterface $request): ?ResponseInterface
    {
        foreach ($this->routes as $route) {
            $targetUrl = $route->matches($request);
            if ($targetUrl !== null) {
                return $this->proxy($request, $route, $targetUrl);
            }
        }

        return null;
    }

    private function proxy(ServerRequestInterface $originalRequest, Route $route, string $targetUrl): ResponseInterface
    {
        $request = $this->buildProxyRequest($originalRequest, $route, $targetUrl);

        // Create the core handler
        $handler = function (RequestInterface $request) {
            return $this->client->sendRequest($request);
        };

        // Merge and sort middlewares by priority (lower priority = outer layer)
        $middlewares = $this->sortMiddlewares(
            array_merge($this->globalMiddlewares, $route->getMiddlewares())
        );

        // Wrap middlewares in reverse order (highest priority = innermost)
        foreach (array_reverse($middlewares) as $middleware) {
            $handler = $this->wrapMiddleware($middleware, $handler);
        }

        return $handler($request);
    }

    private function sortMiddlewares(array $middlewares): array
    {
        usort($middlewares, function ($a, $b) {
            $priorityA = $a instanceof MiddlewareInterface ? ($a->priority ?? 0) : 0;
            $priorityB = $b instanceof MiddlewareInterface ? ($b->priority ?? 0) : 0;

            return $priorityA <=> $priorityB;
        });

        return $middlewares;
    }

    /**
     * @param  callable|MiddlewareInterface  $middleware
     */
    private function wrapMiddleware($middleware, callable $handler): callable
    {
        return function (RequestInterface $request) use ($middleware, $handler) {
            if ($middleware instanceof MiddlewareInterface) {
                return $middleware->process($request, $handler);
            }

            return $middleware($request, $handler);
        };
    }

    private function buildProxyRequest(ServerRequestInterface $originalRequest, Route $route, string $targetUrl): ServerRequestInterface
    {
        // Use withUri to change the URI while preserving attributes
        $request = $originalRequest->withUri(new Uri($targetUrl));

        $targetHost = $route->getTargetHost();
        if ($targetHost !== '') {
            $request = $request->withHeader('Host', $targetHost);
        }

        return $request;
    }
}
