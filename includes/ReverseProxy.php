<?php

namespace ReverseProxy;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Client\ClientExceptionInterface;
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
            $matches = [];
            if ($this->matches($path, $rule['source'], $matches)) {
                $this->proxy($method, $path, $queryString, $rule, $matches);

                return true;
            }
        }

        return false;
    }

    private function proxy(string $method, string $path, string $queryString, array $rule, array $matches = []): void
    {
        $targetUrl = $this->buildTargetUrl($path, $queryString, $rule, $matches);
        $request = $this->requestFactory->createRequest($method, $targetUrl);

        // Forward request headers
        foreach ($this->getRequestHeaders() as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        // Handle Host header
        if (empty($rule['preserve_host'])) {
            // 默認：使用目標主機作為 Host header
            $targetHost = parse_url($rule['target'], PHP_URL_HOST);
            if ($targetHost) {
                $request = $request->withHeader('Host', $targetHost);
            }
        }

        // Forward request body for POST, PUT, PATCH
        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $body = $this->getRequestBody();
            if ($body !== '') {
                $request = $request->withBody($this->streamFactory->createStream($body));
            }
        }

        try {
            $response = $this->client->sendRequest($request);
            $response = apply_filters('reverse_proxy_response', $response, $request);

            $this->sendResponse($response);
        } catch (ClientExceptionInterface $e) {
            do_action('reverse_proxy_error', $e, $request);

            $this->sendErrorResponse(502, 'Bad Gateway: ' . $e->getMessage());
        }
    }

    private function sendErrorResponse(int $statusCode, string $message): void
    {
        if (! headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json');
        }

        echo json_encode(['error' => $message, 'status' => $statusCode]);
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

    private function matches(string $path, string $pattern, ?array &$matches = null): bool
    {
        // 將 * 轉換為捕獲組
        $regex = '#^' . str_replace('\*', '(.*)', preg_quote($pattern, '#')) . '$#';

        if (preg_match($regex, $path, $captured)) {
            $matches = array_slice($captured, 1); // 移除完整匹配，只保留捕獲組

            return true;
        }

        return false;
    }

    private function buildTargetUrl(string $path, string $queryString, array $rule, array $matches = []): string
    {
        // 如果有 rewrite 規則，使用它
        if (isset($rule['rewrite'])) {
            $rewrittenPath = $rule['rewrite'];

            // 替換 $1, $2, ... 為捕獲的匹配
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

    private function createDefaultClient(): ClientInterface
    {
        return new \Http\Mock\Client();
    }
}
