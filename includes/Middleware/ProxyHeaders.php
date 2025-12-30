<?php

namespace ReverseProxy\Middleware;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use ReverseProxy\Contracts\MiddlewareInterface;

class ProxyHeaders implements MiddlewareInterface
{
    public function process(RequestInterface $request, callable $next): ResponseInterface
    {
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
        $originalHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
        $originalScheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
        $originalPort = $_SERVER['SERVER_PORT'] ?? ($originalScheme === 'https' ? '443' : '80');

        $request = $request
            ->withHeader('X-Real-IP', $clientIp)
            ->withHeader('X-Forwarded-For', $this->buildForwardedFor($request, $clientIp))
            ->withHeader('X-Forwarded-Host', $originalHost)
            ->withHeader('X-Forwarded-Proto', $originalScheme)
            ->withHeader('X-Forwarded-Port', $originalPort)
            ->withHeader('Forwarded', $this->buildForwarded($request, $clientIp, $originalHost, $originalScheme));

        return $next($request);
    }

    private function buildForwarded(RequestInterface $request, string $clientIp, string $host, string $proto): string
    {
        $existing = $request->getHeaderLine('Forwarded');

        $parts = [];
        if ($clientIp !== '') {
            // Quote IPv6 addresses per RFC 7239
            $parts[] = strpos($clientIp, ':') !== false
                ? 'for="['.$clientIp.']"'
                : 'for='.$clientIp;
        }
        if ($host !== '') {
            $parts[] = 'host='.$host;
        }
        if ($proto !== '') {
            $parts[] = 'proto='.$proto;
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
