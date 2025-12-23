<?php

namespace ReverseProxy;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class ReverseProxy
{
    /** @var ClientInterface */
    private $client;

    /** @var RequestFactoryInterface */
    private $requestFactory;

    /** @var StreamFactoryInterface */
    private $streamFactory;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        ClientInterface $client,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        ?LoggerInterface $logger = null
    ) {
        $this->client = $client;
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * @param ServerRequestInterface $request
     * @param Rule[] $rules
     * @return ResponseInterface|null
     */
    public function handle(ServerRequestInterface $request, array $rules): ?ResponseInterface
    {
        foreach ($rules as $rule) {
            $targetUrl = $rule->matches($request);
            if ($targetUrl !== null) {
                return $this->proxy($request, $rule, $targetUrl);
            }
        }

        return null;
    }

    private function proxy(ServerRequestInterface $originalRequest, Rule $rule, string $targetUrl): ResponseInterface
    {
        $request = $this->buildProxyRequest($originalRequest, $rule, $targetUrl);

        // Create the core handler
        $handler = function (RequestInterface $request) use ($originalRequest, $targetUrl) {
            return $this->sendRequest($request, $originalRequest, $targetUrl);
        };

        // Wrap with middlewares (in reverse order)
        foreach (array_reverse($rule->getMiddlewares()) as $middleware) {
            $handler = function (RequestInterface $request) use ($middleware, $handler) {
                return $middleware($request, $handler);
            };
        }

        return $handler($request);
    }

    private function sendRequest(RequestInterface $request, ServerRequestInterface $originalRequest, string $targetUrl): ResponseInterface
    {
        $this->logger->info('Proxying request', [
            'method' => $request->getMethod(),
            'source' => $originalRequest->getUri()->getPath(),
            'target' => $targetUrl,
        ]);

        try {
            $response = $this->client->sendRequest($request);

            $this->logger->info('Proxy response received', [
                'status' => $response->getStatusCode(),
                'target' => $targetUrl,
            ]);

            return $response;
        } catch (ClientExceptionInterface $e) {
            $this->logger->error('Proxy error: ' . $e->getMessage(), [
                'target' => $targetUrl,
                'exception' => get_class($e),
            ]);

            return $this->createErrorResponse(502, 'Bad Gateway: ' . $e->getMessage());
        }
    }

    private function buildProxyRequest(ServerRequestInterface $originalRequest, Rule $rule, string $targetUrl): RequestInterface
    {
        $method = $originalRequest->getMethod();
        $request = $this->requestFactory->createRequest($method, $targetUrl);

        foreach ($originalRequest->getHeaders() as $name => $values) {
            $request = $request->withHeader($name, $values);
        }

        if (! $rule->shouldPreserveHost()) {
            $targetHost = $rule->getTargetHost();
            if ($targetHost !== '') {
                $request = $request->withHeader('Host', $targetHost);
            }
        }

        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $body = (string) $originalRequest->getBody();
            if ($body !== '') {
                $request = $request->withBody($this->streamFactory->createStream($body));
            }
        }

        return $request;
    }

    private function createErrorResponse(int $statusCode, string $message): ResponseInterface
    {
        $body = json_encode(['error' => $message, 'status' => $statusCode]);

        return new \Nyholm\Psr7\Response(
            $statusCode,
            ['Content-Type' => 'application/json'],
            $body
        );
    }
}
