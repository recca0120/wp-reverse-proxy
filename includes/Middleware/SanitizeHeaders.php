<?php

namespace Recca0120\ReverseProxy\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Recca0120\ReverseProxy\Contracts\MiddlewareInterface;

class SanitizeHeaders implements MiddlewareInterface
{
    /**
     * Headers that should not be forwarded (hop-by-hop headers).
     *
     * @var string[]
     */
    private const HOP_BY_HOP_HEADERS = [
        'transfer-encoding',
        'connection',
        'keep-alive',
        'proxy-authenticate',
        'proxy-authorization',
        'te',
        'trailer',
        'upgrade',
    ];

    /** @var int */
    public $priority = -1000;

    public function process(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        $request = $this->removeBrotliEncoding($request);
        $response = $next($request);

        return $this->removeHopByHopHeaders($response);
    }

    private function removeBrotliEncoding(ServerRequestInterface $request): ServerRequestInterface
    {
        $encoding = $request->getHeaderLine('Accept-Encoding');

        if ($encoding === '') {
            return $request;
        }

        $filtered = preg_replace('/\bbr\b\s*,?\s*/', '', $encoding);
        $filtered = rtrim($filtered, ', ');

        if ($filtered === '') {
            return $request->withoutHeader('Accept-Encoding');
        }

        return $request->withHeader('Accept-Encoding', $filtered);
    }

    private function removeHopByHopHeaders(ResponseInterface $response): ResponseInterface
    {
        foreach (self::HOP_BY_HOP_HEADERS as $header) {
            $response = $response->withoutHeader($header);
        }

        return $response;
    }
}
