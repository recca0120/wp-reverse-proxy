<?php

namespace ReverseProxy\Http\Plugin;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use ReverseProxy\Http\PluginInterface;

class RemoveHopByHopHeadersPlugin implements PluginInterface
{
    /**
     * Headers that should not be forwarded (hop-by-hop headers).
     *
     * @var string[]
     */
    private const FILTERED_HEADERS = [
        'transfer-encoding',
        'connection',
        'keep-alive',
        'proxy-authenticate',
        'proxy-authorization',
        'te',
        'trailer',
        'upgrade',
    ];

    public function filterRequest(RequestInterface $request): RequestInterface
    {
        return $request;
    }

    public function filterResponse(ResponseInterface $response): ResponseInterface
    {
        foreach (self::FILTERED_HEADERS as $header) {
            $response = $response->withoutHeader($header);
        }

        return $response;
    }
}
