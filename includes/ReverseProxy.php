<?php

namespace ReverseProxy;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use WP;

class ReverseProxy
{
    private ClientInterface $client;
    private RequestFactoryInterface $requestFactory;

    public function __construct(?ClientInterface $client = null, ?RequestFactoryInterface $requestFactory = null)
    {
        $this->requestFactory = $requestFactory ?? new Psr17Factory();
        $this->client = $client ?? apply_filters('reverse_proxy_http_client', $this->createDefaultClient());
    }

    public function handle(WP $wp): bool
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($requestUri, PHP_URL_PATH) ?: '/';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $rules = apply_filters('reverse_proxy_rules', []);

        foreach ($rules as $rule) {
            if ($this->matches($path, $rule['source'])) {
                $this->proxy($method, $path, $rule);

                return true;
            }
        }

        return false;
    }

    private function proxy(string $method, string $path, array $rule): void
    {
        $targetUrl = $this->buildTargetUrl($path, $rule);
        $request = $this->requestFactory->createRequest($method, $targetUrl);
        $response = $this->client->sendRequest($request);

        $this->sendResponse($response);
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

    private function buildTargetUrl(string $path, array $rule): string
    {
        return rtrim($rule['target'], '/') . $path;
    }

    private function createDefaultClient(): ClientInterface
    {
        return new \Http\Mock\Client();
    }
}
