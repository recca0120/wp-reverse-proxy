<?php

namespace ReverseProxy;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
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
            $matchResult = $rule->matches($request);
            if ($matchResult !== null) {
                return $this->proxy($request, $matchResult);
            }
        }

        return null;
    }

    private function proxy(ServerRequestInterface $originalRequest, MatchResult $matchResult): ResponseInterface
    {
        $rule = $matchResult->getRule();
        $targetUrl = $matchResult->getTargetUrl();
        $method = $originalRequest->getMethod();

        $request = $this->requestFactory->createRequest($method, $targetUrl);

        // Forward request headers
        foreach ($originalRequest->getHeaders() as $name => $values) {
            $request = $request->withHeader($name, $values);
        }

        // Handle Host header
        if (! $rule->shouldPreserveHost()) {
            $targetHost = $rule->getTargetHost();
            if ($targetHost !== '') {
                $request = $request->withHeader('Host', $targetHost);
            }
        }

        // Forward request body for POST, PUT, PATCH
        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $body = (string) $originalRequest->getBody();
            if ($body !== '') {
                $request = $request->withBody($this->streamFactory->createStream($body));
            }
        }

        $this->logger->info('Proxying request', [
            'method' => $method,
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
