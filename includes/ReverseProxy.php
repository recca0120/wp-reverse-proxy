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

    /** @var ErrorHandlerInterface|null */
    private $errorHandler;

    public function __construct(
        ClientInterface $client,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        ?LoggerInterface $logger = null,
        ?ErrorHandlerInterface $errorHandler = null
    ) {
        $this->client = $client;
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
        $this->logger = $logger ?? new NullLogger();
        $this->errorHandler = $errorHandler;
    }

    public function handle(ServerRequestInterface $request, array $rules): ?ResponseInterface
    {
        $path = $request->getUri()->getPath() ?: '/';

        foreach ($rules as $rule) {
            $matches = [];
            if ($this->matches($path, $rule['source'], $matches)) {
                return $this->proxy($request, $rule, $matches);
            }
        }

        return null;
    }

    private function proxy(ServerRequestInterface $originalRequest, array $rule, array $matches = []): ResponseInterface
    {
        $method = $originalRequest->getMethod();
        $uri = $originalRequest->getUri();
        $path = $uri->getPath() ?: '/';
        $queryString = $uri->getQuery() ?: '';

        $targetUrl = $this->buildTargetUrl($path, $queryString, $rule, $matches);
        $request = $this->requestFactory->createRequest($method, $targetUrl);

        // Forward request headers
        foreach ($originalRequest->getHeaders() as $name => $values) {
            $request = $request->withHeader($name, $values);
        }

        // Handle Host header
        if (empty($rule['preserve_host'])) {
            $targetHost = parse_url($rule['target'], PHP_URL_HOST);
            if ($targetHost) {
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
            'source' => $path,
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

            if ($this->errorHandler) {
                $this->errorHandler->handle($e, $request);
            }

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

    private function matches(string $path, string $pattern, ?array &$matches = null): bool
    {
        $regex = '#^' . str_replace('\*', '(.*)', preg_quote($pattern, '#')) . '$#';

        if (preg_match($regex, $path, $captured)) {
            $matches = array_slice($captured, 1);

            return true;
        }

        return false;
    }

    private function buildTargetUrl(string $path, string $queryString, array $rule, array $matches = []): string
    {
        if (isset($rule['rewrite'])) {
            $rewrittenPath = $rule['rewrite'];

            foreach ($matches as $index => $match) {
                $rewrittenPath = str_replace('$' . ($index + 1), $match, $rewrittenPath);
            }

            $url = rtrim($rule['target'], '/') . $rewrittenPath;
        } else {
            $url = rtrim($rule['target'], '/') . $path;
        }

        if ($queryString !== '') {
            $url .= '?' . $queryString;
        }

        return $url;
    }
}
