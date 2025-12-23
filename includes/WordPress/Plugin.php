<?php

namespace ReverseProxy\WordPress;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use ReverseProxy\Http\WordPressHttpClient;
use ReverseProxy\ReverseProxy;

class Plugin
{
    /** @var ReverseProxy */
    private $reverseProxy;

    /** @var ServerRequestFactory */
    private $serverRequestFactory;

    public function __construct(ReverseProxy $reverseProxy, ServerRequestFactory $serverRequestFactory)
    {
        $this->reverseProxy = $reverseProxy;
        $this->serverRequestFactory = $serverRequestFactory;
    }

    public static function create(?ClientInterface $httpClient = null): self
    {
        $psr17Factory = new Psr17Factory();

        $httpClient = $httpClient ?? new WordPressHttpClient();
        $logger = new Logger();

        $reverseProxy = new ReverseProxy(
            $httpClient,
            $psr17Factory,
            $psr17Factory,
            $logger
        );

        $serverRequestFactory = new ServerRequestFactory($psr17Factory);

        return new self($reverseProxy, $serverRequestFactory);
    }

    /**
     * @param array $rules
     * @return ResponseInterface|null
     */
    public function handle(array $rules): ?ResponseInterface
    {
        $request = $this->serverRequestFactory->createFromGlobals();

        return $this->reverseProxy->handle($request, $rules);
    }

    public function emit(ResponseInterface $response): void
    {
        if (! headers_sent()) {
            http_response_code($response->getStatusCode());
            foreach ($response->getHeaders() as $name => $values) {
                foreach ($values as $value) {
                    header("{$name}: {$value}", false);
                }
            }
        }

        echo $response->getBody();
    }
}
