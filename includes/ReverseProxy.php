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
use Recca0120\ReverseProxy\Routing\Route;
use Recca0120\ReverseProxy\Routing\RouteCollection;
use Recca0120\ReverseProxy\Support\Arr;

class ReverseProxy
{
    /** @var RouteCollection */
    private $routes;

    /** @var ClientInterface */
    private $client;

    /** @var RequestFactoryInterface */
    private $requestFactory;

    /** @var StreamFactoryInterface */
    private $streamFactory;

    public function __construct(
        RouteCollection $routes,
        ClientInterface $client,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory
    ) {
        $this->routes = $routes;
        $this->client = $client;
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
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

        // Merge global middlewares from manager with route middlewares
        $globalMiddlewares = $this->routes->getMiddlewareManager()->getGlobalMiddlewares();
        $middlewares = $this->sortMiddlewares(
            Arr::merge($globalMiddlewares, $route->getMiddlewares())
        );

        // Wrap middlewares in reverse order (highest priority = innermost)
        foreach (array_reverse($middlewares) as $middleware) {
            $handler = $this->wrapMiddleware($middleware, $handler);
        }

        return $handler($request);
    }

    private function sortMiddlewares(array $middlewares): array
    {
        $indexed = [];
        foreach ($middlewares as $index => $middleware) {
            $priority = $middleware instanceof MiddlewareInterface ? ($middleware->priority ?? 0) : 0;
            $indexed[] = [$priority, $index, $middleware];
        }

        usort($indexed, function ($a, $b) {
            // Sort by priority first, then by original index (stable sort)
            return $a[0] <=> $b[0] ?: $a[1] <=> $b[1];
        });

        return array_column($indexed, 2);
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
