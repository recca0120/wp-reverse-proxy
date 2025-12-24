<?php

namespace ReverseProxy\WordPress;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Message\ResponseInterface;
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

    public function __construct(
        ReverseProxy $reverseProxy,
        ServerRequestFactory $serverRequestFactory,
        ResponseEmitter $responseEmitter
    ) {
        $this->reverseProxy = $reverseProxy;
        $this->serverRequestFactory = $serverRequestFactory;
        $this->responseEmitter = $responseEmitter;
    }

    public static function create(): self
    {
        $requestFactory = Psr17FactoryDiscovery::findRequestFactory();
        $streamFactory = Psr17FactoryDiscovery::findStreamFactory();
        $httpClient = apply_filters('reverse_proxy_http_client', Psr18ClientDiscovery::find());

        $reverseProxy = new ReverseProxy($httpClient, $requestFactory, $streamFactory);
        $reverseProxy->addGlobalMiddlewares(apply_filters('reverse_proxy_default_middlewares', [
            new ErrorHandlingMiddleware(),
            new LoggingMiddleware(new Logger()),
        ]));

        $serverRequestFactory = new ServerRequestFactory($streamFactory);
        $responseEmitter = new ResponseEmitter();

        return new self($reverseProxy, $serverRequestFactory, $responseEmitter);
    }

    /**
     * @param Route[] $routes
     * @return ResponseInterface|null
     */
    public function handle(array $routes): ?ResponseInterface
    {
        $request = $this->serverRequestFactory->createFromGlobals();

        return $this->reverseProxy->handle($request, $routes);
    }

    public function emit(ResponseInterface $response): void
    {
        $this->responseEmitter->emit($response);
    }
}
