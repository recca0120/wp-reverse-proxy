<?php

namespace ReverseProxy\WordPress;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use ReverseProxy\Http\WordPressHttpClient;
use ReverseProxy\Middleware\ErrorHandlingMiddleware;
use ReverseProxy\Middleware\LoggingMiddleware;
use ReverseProxy\ReverseProxy;
use ReverseProxy\Route;

class Plugin
{
    /** @var ReverseProxy */
    private $reverseProxy;

    /** @var ServerRequestFactory */
    private $serverRequestFactory;

    /** @var ResponseEmitter */
    private $responseEmitter;

    /** @var array */
    private $defaultMiddlewares;

    public function __construct(
        ReverseProxy $reverseProxy,
        ServerRequestFactory $serverRequestFactory,
        ResponseEmitter $responseEmitter,
        array $defaultMiddlewares = []
    ) {
        $this->reverseProxy = $reverseProxy;
        $this->serverRequestFactory = $serverRequestFactory;
        $this->responseEmitter = $responseEmitter;
        $this->defaultMiddlewares = $defaultMiddlewares;
    }

    public static function create(?ClientInterface $httpClient = null): self
    {
        $psr17Factory = new Psr17Factory();
        $httpClient = $httpClient ?? new WordPressHttpClient();

        $reverseProxy = new ReverseProxy(
            $httpClient,
            $psr17Factory,
            $psr17Factory
        );

        $serverRequestFactory = new ServerRequestFactory($psr17Factory);
        $responseEmitter = new ResponseEmitter();

        $defaultMiddlewares = [
            new ErrorHandlingMiddleware(),
            new LoggingMiddleware(new Logger()),
        ];

        return new self($reverseProxy, $serverRequestFactory, $responseEmitter, $defaultMiddlewares);
    }

    /**
     * @param Route[] $routes
     * @return ResponseInterface|null
     */
    public function handle(array $routes): ?ResponseInterface
    {
        $request = $this->serverRequestFactory->createFromGlobals();
        $routes = $this->wrapRoutesWithDefaultMiddlewares($routes);

        return $this->reverseProxy->handle($request, $routes);
    }

    /**
     * @param Route[] $routes
     * @return Route[]
     */
    private function wrapRoutesWithDefaultMiddlewares(array $routes): array
    {
        foreach ($routes as $route) {
            foreach ($this->defaultMiddlewares as $middleware) {
                $route->middleware($middleware);
            }
        }

        return $routes;
    }

    public function emit(ResponseInterface $response): void
    {
        $this->responseEmitter->emit($response);
    }
}
