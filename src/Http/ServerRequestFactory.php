<?php

namespace Recca0120\ReverseProxy\Http;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Uri;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Recca0120\ReverseProxy\Support\Arr;
use Recca0120\ReverseProxy\Support\Str;

class ServerRequestFactory
{
    /** @var StreamFactoryInterface */
    private $streamFactory;

    public function __construct(?StreamFactoryInterface $streamFactory = null)
    {
        $this->streamFactory = $streamFactory ?? new Psr17Factory();
    }

    public static function createFromGlobals(?StreamFactoryInterface $streamFactory = null): ServerRequestInterface
    {
        return (new self($streamFactory))->create(
            $_SERVER,
            function () {
                return file_get_contents('php://input') ?: '';
            }
        );
    }

    /**
     * @param  string|callable  $body
     */
    public function create(array $serverParams, $body = ''): ServerRequestInterface
    {
        $method = $serverParams['REQUEST_METHOD'] ?? 'GET';
        $uri = $this->createUri($serverParams);
        $headers = $this->getHeaders($serverParams);
        $bodyContent = $this->resolveBody($method, $body);

        $request = new ServerRequest($method, $uri, $headers, null, '1.1', $serverParams);

        if ($bodyContent !== '') {
            $request = $request->withBody($this->streamFactory->createStream($bodyContent));
        }

        return $request;
    }

    private function createUri(array $serverParams): Uri
    {
        $scheme = ($serverParams['HTTPS'] ?? 'off') !== 'off' ? 'https' : 'http';
        $host = $serverParams['HTTP_HOST'] ?? $serverParams['SERVER_NAME'] ?? 'localhost';
        $requestUri = $this->normalizeRequestUri($serverParams['REQUEST_URI'] ?? '/');

        return new Uri($scheme.'://'.$host.$requestUri);
    }

    private function normalizeRequestUri(string $requestUri): string
    {
        $parts = explode('?', $requestUri, 2);
        $path = preg_replace('#/+#', '/', $parts[0]);

        return isset($parts[1]) ? $path.'?'.$parts[1] : $path;
    }

    private function getHeaders(array $serverParams): array
    {
        $headers = [];

        foreach ($serverParams as $key => $value) {
            if (Str::startsWith($key, 'HTTP_')) {
                // Skip CONTENT_TYPE and CONTENT_LENGTH as they're handled separately
                if ($key === 'HTTP_CONTENT_TYPE' || $key === 'HTTP_CONTENT_LENGTH') {
                    continue;
                }
                // Skip empty values
                if ($value === '' || $value === null) {
                    continue;
                }
                $name = str_replace('_', '-', Str::after($key, 'HTTP_'));
                $headers[$name] = $value;
            } elseif ($key === 'CONTENT_TYPE' && $value) {
                $headers['Content-Type'] = $value;
            } elseif ($key === 'CONTENT_LENGTH' && $value) {
                $headers['Content-Length'] = $value;
            }
        }

        return $headers;
    }

    /**
     * @param  string|callable  $body
     */
    private function resolveBody(string $method, $body): string
    {
        if (! Arr::contains(['POST', 'PUT', 'PATCH'], $method)) {
            return '';
        }

        return is_callable($body) ? $body() : $body;
    }
}
