<?php

namespace ReverseProxy\Middleware;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use ReverseProxy\Contracts\MiddlewareInterface;

class ProxyHeaders implements MiddlewareInterface
{
    /** @var string */
    private $clientIp;

    /** @var string */
    private $host;

    /** @var string */
    private $scheme;

    /** @var string */
    private $port;

    public function __construct(
        ?string $clientIp = null,
        ?string $host = null,
        ?string $scheme = null,
        ?string $port = null
    ) {
        $this->clientIp = $clientIp ?? $_SERVER['REMOTE_ADDR'] ?? '';
        $this->host = $host ?? $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
        $this->scheme = $scheme ?? (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http');
        $this->port = $port ?? $_SERVER['SERVER_PORT'] ?? ($this->scheme === 'https' ? '443' : '80');
    }

    public function process(RequestInterface $request, callable $next): ResponseInterface
    {
        $request = $request
            ->withHeader('X-Real-IP', $this->clientIp)
            ->withHeader('X-Forwarded-For', $this->buildForwardedFor($request))
            ->withHeader('X-Forwarded-Host', $this->host)
            ->withHeader('X-Forwarded-Proto', $this->scheme)
            ->withHeader('X-Forwarded-Port', $this->port)
            ->withHeader('Forwarded', $this->buildForwarded($request));

        return $next($request);
    }

    private function buildForwarded(RequestInterface $request): string
    {
        $existing = $request->getHeaderLine('Forwarded');

        $parts = [];
        if ($this->clientIp !== '') {
            // Quote IPv6 addresses per RFC 7239
            $parts[] = strpos($this->clientIp, ':') !== false
                ? 'for="['.$this->clientIp.']"'
                : 'for='.$this->clientIp;
        }
        if ($this->host !== '') {
            $parts[] = 'host='.$this->host;
        }
        if ($this->scheme !== '') {
            $parts[] = 'proto='.$this->scheme;
        }

        $current = implode(';', $parts);

        if ($existing === '' || $current === '') {
            return $existing ?: $current;
        }

        return $existing.', '.$current;
    }

    private function buildForwardedFor(RequestInterface $request): string
    {
        $existing = $request->getHeaderLine('X-Forwarded-For');

        if ($existing === '' || $this->clientIp === '') {
            return $existing ?: $this->clientIp;
        }

        return "{$existing}, {$this->clientIp}";
    }
}
