<?php

namespace ReverseProxy\Middleware;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use ReverseProxy\MiddlewareInterface;

class ProxyHeadersMiddleware implements MiddlewareInterface
{
    public function process(RequestInterface $request, callable $next): ResponseInterface
    {
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';

        $request = $request
            ->withHeader('X-Real-IP', $clientIp)
            ->withHeader('X-Forwarded-For', $this->buildForwardedFor($request, $clientIp))
            ->withHeader('X-Forwarded-Proto', $request->getUri()->getScheme())
            ->withHeader('X-Forwarded-Port', $this->getPort($request));

        return $next($request);
    }

    private function buildForwardedFor(RequestInterface $request, string $clientIp): string
    {
        $existing = $request->getHeaderLine('X-Forwarded-For');

        if ($existing === '' || $clientIp === '') {
            return $existing ?: $clientIp;
        }

        return "{$existing}, {$clientIp}";
    }

    private function getPort(RequestInterface $request): string
    {
        $uri = $request->getUri();
        $port = $uri->getPort();

        if ($port !== null) {
            return (string) $port;
        }

        return $uri->getScheme() === 'https' ? '443' : '80';
    }
}
