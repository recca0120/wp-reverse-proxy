<?php

namespace ReverseProxy\Middleware;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReverseProxy\Contracts\MiddlewareInterface;

class ProxyHeaders implements MiddlewareInterface
{
    private const ALL_HEADERS = [
        'X-Real-IP',
        'X-Forwarded-For',
        'X-Forwarded-Host',
        'X-Forwarded-Proto',
        'X-Forwarded-Port',
        'Forwarded',
    ];

    /** @var array<string, mixed> */
    private $options;

    /**
     * @param array<string, mixed> $options {
     *     @type string $clientIp  Override client IP
     *     @type string $host      Override host
     *     @type string $scheme    Override scheme (http/https)
     *     @type string $port      Override port
     *     @type array  $headers   Only include these headers
     *     @type array  $except    Exclude these headers
     * }
     */
    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    public function process(RequestInterface $request, callable $next): ResponseInterface
    {
        $serverParams = $this->getServerParams($request);

        $clientIp = $this->options['clientIp'] ?? $serverParams['REMOTE_ADDR'] ?? '';
        $host = $this->options['host'] ?? $serverParams['HTTP_HOST'] ?? $serverParams['SERVER_NAME'] ?? '';
        $scheme = $this->options['scheme'] ?? $this->detectScheme($serverParams);
        $port = $this->options['port'] ?? $serverParams['SERVER_PORT'] ?? ($scheme === 'https' ? '443' : '80');

        $headers = [
            'X-Real-IP' => $clientIp,
            'X-Forwarded-For' => $this->buildForwardedFor($request, $clientIp),
            'X-Forwarded-Host' => $host,
            'X-Forwarded-Proto' => $scheme,
            'X-Forwarded-Port' => $port,
            'Forwarded' => $this->buildForwarded($request, $clientIp, $host, $scheme),
        ];

        foreach ($this->filterHeaders($headers) as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $next($request);
    }

    /**
     * @return array<string, mixed>
     */
    private function getServerParams(RequestInterface $request): array
    {
        if ($request instanceof ServerRequestInterface) {
            $serverParams = $request->getServerParams();
            if (! empty($serverParams)) {
                return $serverParams;
            }
        }

        return $_SERVER;
    }

    /**
     * @param array<string, mixed> $serverParams
     */
    private function detectScheme(array $serverParams): string
    {
        if (isset($serverParams['HTTPS']) && $serverParams['HTTPS'] !== 'off') {
            return 'https';
        }

        return 'http';
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    private function filterHeaders(array $headers): array
    {
        $allowedHeaders = $this->options['headers'] ?? self::ALL_HEADERS;
        $exceptHeaders = $this->options['except'] ?? [];

        return array_filter(
            $headers,
            function ($name) use ($allowedHeaders, $exceptHeaders) {
                return in_array($name, $allowedHeaders, true)
                    && ! in_array($name, $exceptHeaders, true);
            },
            ARRAY_FILTER_USE_KEY
        );
    }

    private function buildForwarded(
        RequestInterface $request,
        string $clientIp,
        string $host,
        string $scheme
    ): string {
        $existing = $request->getHeaderLine('Forwarded');

        $parts = [];
        if ($clientIp !== '') {
            $parts[] = strpos($clientIp, ':') !== false
                ? 'for="['.$clientIp.']"'
                : 'for='.$clientIp;
        }
        if ($host !== '') {
            $parts[] = 'host='.$host;
        }
        if ($scheme !== '') {
            $parts[] = 'proto='.$scheme;
        }

        $current = implode(';', $parts);

        if ($existing === '' || $current === '') {
            return $existing ?: $current;
        }

        return $existing.', '.$current;
    }

    private function buildForwardedFor(RequestInterface $request, string $clientIp): string
    {
        $existing = $request->getHeaderLine('X-Forwarded-For');

        if ($existing === '' || $clientIp === '') {
            return $existing ?: $clientIp;
        }

        return "{$existing}, {$clientIp}";
    }
}
