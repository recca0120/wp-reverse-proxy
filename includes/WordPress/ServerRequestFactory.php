<?php

namespace ReverseProxy\WordPress;

use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Uri;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;

class ServerRequestFactory
{
    /** @var StreamFactoryInterface */
    private $streamFactory;

    public function __construct(StreamFactoryInterface $streamFactory)
    {
        $this->streamFactory = $streamFactory;
    }

    public function createFromGlobals(): ServerRequestInterface
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $this->createUri();
        $headers = $this->getHeaders();
        $body = $this->getBody($method);

        $request = new ServerRequest($method, $uri, $headers);

        if ($body !== '') {
            $request = $request->withBody($this->streamFactory->createStream($body));
        }

        return $request;
    }

    private function createUri(): Uri
    {
        $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
        $requestUri = $this->normalizeRequestUri($_SERVER['REQUEST_URI'] ?? '/');

        return new Uri($scheme.'://'.$host.$requestUri);
    }

    private function normalizeRequestUri(string $requestUri): string
    {
        $parts = explode('?', $requestUri, 2);
        $path = preg_replace('#/+#', '/', $parts[0]);

        return isset($parts[1]) ? $path.'?'.$parts[1] : $path;
    }

    private function getHeaders(): array
    {
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                // Skip CONTENT_TYPE and CONTENT_LENGTH as they're handled separately
                if ($key === 'HTTP_CONTENT_TYPE' || $key === 'HTTP_CONTENT_LENGTH') {
                    continue;
                }
                // Skip empty values
                if ($value === '' || $value === null) {
                    continue;
                }
                $name = str_replace('_', '-', substr($key, 5));
                $headers[$name] = $value;
            } elseif ($key === 'CONTENT_TYPE' && $value) {
                $headers['Content-Type'] = $value;
            } elseif ($key === 'CONTENT_LENGTH' && $value) {
                $headers['Content-Length'] = $value;
            }
        }

        return $headers;
    }

    private function getBody(string $method): string
    {
        if (! in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            return '';
        }

        // Allow filter for testing
        $body = apply_filters('reverse_proxy_request_body', null);
        if ($body !== null) {
            return $body;
        }

        return file_get_contents('php://input') ?: '';
    }
}
