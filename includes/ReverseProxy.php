<?php

namespace ReverseProxy;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;

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
     * @param callable|MiddlewareInterface $middleware
     * @return self
     */
    public function addGlobalMiddleware($middleware): self
    {
        $this->globalMiddlewares[] = $middleware;

        return $this;
    }

    /**
     * @param ServerRequestInterface $request
     * @param Route[] $routes
     * @return ResponseInterface|null
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

        // Wrap with route middlewares (in reverse order)
        foreach (array_reverse($route->getMiddlewares()) as $middleware) {
            $handler = $this->wrapMiddleware($middleware, $handler);
        }

        // Wrap with global middlewares (in reverse order, so first added is outermost)
        foreach (array_reverse($this->globalMiddlewares) as $middleware) {
            $handler = $this->wrapMiddleware($middleware, $handler);
        }

        return $handler($request);
    }

    /**
     * @param callable|MiddlewareInterface $middleware
     * @param callable $handler
     * @return callable
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

    private function buildProxyRequest(ServerRequestInterface $originalRequest, Route $route, string $targetUrl): RequestInterface
    {
        $method = $originalRequest->getMethod();
        $request = $this->requestFactory->createRequest($method, $targetUrl);

        foreach ($originalRequest->getHeaders() as $name => $values) {
            $request = $request->withHeader($name, $values);
        }

        $targetHost = $route->getTargetHost();
        if ($targetHost !== '') {
            $request = $request->withHeader('Host', $targetHost);
        }

        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $body = (string) $originalRequest->getBody();
            if ($body !== '') {
                $request = $request->withBody($this->streamFactory->createStream($body));
            }
        }

        return $request;
    }
}
