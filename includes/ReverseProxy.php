<?php

namespace ReverseProxy;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use WP;

class ReverseProxy
{
    private ClientInterface $client;
    private RequestFactoryInterface $requestFactory;
    private StreamFactoryInterface $streamFactory;

    public function __construct(?ClientInterface $client = null, ?RequestFactoryInterface $requestFactory = null, ?StreamFactoryInterface $streamFactory = null)
    {
        $psr17Factory = new Psr17Factory();
        $this->requestFactory = $requestFactory ?? $psr17Factory;
        $this->streamFactory = $streamFactory ?? $psr17Factory;
        $this->client = $client ?? apply_filters('reverse_proxy_http_client', $this->createDefaultClient());
    }

    public function handle(WP $wp): bool
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($requestUri, PHP_URL_PATH) ?: '/';
        $queryString = parse_url($requestUri, PHP_URL_QUERY) ?: '';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $rules = apply_filters('reverse_proxy_rules', []);

        foreach ($rules as $rule) {
            if ($this->matches($path, $rule['source'])) {
                $this->proxy($method, $path, $queryString, $rule);

                return true;
            }
        }

        return false;
    }

    private function proxy(string $method, string $path, string $queryString, array $rule): void
    {
        $targetUrl = $this->buildTargetUrl($path, $queryString, $rule);
        $request = $this->requestFactory->createRequest($method, $targetUrl);

        // Forward request headers
        foreach ($this->getRequestHeaders() as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        // Forward request body for POST, PUT, PATCH
        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $body = $this->getRequestBody();
            if ($body !== '') {
                $request = $request->withBody($this->streamFactory->createStream($body));
            }
        }

        $response = $this->client->sendRequest($request);

        $this->sendResponse($response);
    }

    private function getRequestHeaders(): array
    {
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                // HTTP_X_CUSTOM_HEADER -> X-Custom-Header
                $name = str_replace('_', '-', substr($key, 5));
                $name = ucwords(strtolower($name), '-');
                $headers[$name] = $value;
            } elseif ($key === 'CONTENT_TYPE' && $value) {
                $headers['Content-Type'] = $value;
            } elseif ($key === 'CONTENT_LENGTH' && $value) {
                $headers['Content-Length'] = $value;
            }
        }

        return $headers;
    }

    private function getRequestBody(): string
    {
        $body = apply_filters('reverse_proxy_request_body', null);

        if ($body !== null) {
            return $body;
        }

        return file_get_contents('php://input') ?: '';
    }

    private function sendResponse(\Psr\Http\Message\ResponseInterface $response): void
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

    private function matches(string $path, string $pattern): bool
    {
        $regex = '#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '$#';

        return preg_match($regex, $path) === 1;
    }

    private function buildTargetUrl(string $path, string $queryString, array $rule): string
    {
        $url = rtrim($rule['target'], '/') . $path;

        if ($queryString !== '') {
            $url .= '?' . $queryString;
        }

        return $url;
    }

    private function createDefaultClient(): ClientInterface
    {
        return new \Http\Mock\Client();
    }
}
