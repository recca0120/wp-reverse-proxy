<?php

namespace ReverseProxy\WordPress;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Client as HttpClient;
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
        $psr17Factory = new Psr17Factory();
        $httpClient = apply_filters('reverse_proxy_http_client', new HttpClient());

        $reverseProxy = new ReverseProxy($httpClient, $psr17Factory, $psr17Factory);
        $reverseProxy->addGlobalMiddlewares(apply_filters('reverse_proxy_default_middlewares', [
            new ErrorHandlingMiddleware(),
            new LoggingMiddleware(new Logger()),
        ]));

        $serverRequestFactory = new ServerRequestFactory($psr17Factory);
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
