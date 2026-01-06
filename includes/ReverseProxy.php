<?php

namespace ReverseProxy;

use Nyholm\Psr7\Uri;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use ReverseProxy\Contracts\MiddlewareInterface;

class ReverseProxy
{
    /** @var ClientInterface */
    private $client;

    /** @var RequestFactoryInterface */
    private $requestFactory;

    /** @var StreamFactoryInterface */
    private $streamFactory;

    /** @var array */
    private $globalMiddlewares = [];

    public function __construct(
        ClientInterface $client,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory
    ) {
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

    /**
     * @param  Route[]  $routes
     */
    public function handle(ServerRequestInterface $request, array $routes): ?ResponseInterface
    {
        foreach ($routes as $route) {
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
        usort($middlewares, fn ($a, $b): int =>
            ($a instanceof MiddlewareInterface ? ($a->priority ?? 0) : 0) <=>
            ($b instanceof MiddlewareInterface ? ($b->priority ?? 0) : 0)
        );

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
