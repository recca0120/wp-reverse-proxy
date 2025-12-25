<?php

namespace ReverseProxy\Http\Plugin;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use ReverseProxy\Http\PluginInterface;

class RemoveBrotliEncodingPlugin implements PluginInterface
{
    public function filterRequest(RequestInterface $request): RequestInterface
    {
        $encoding = $request->getHeaderLine('Accept-Encoding');

        if ($encoding === '') {
            return $request;
        }

        $filtered = $this->removeBrotli($encoding);

        if ($filtered === '') {
            return $request->withoutHeader('Accept-Encoding');
        }

        return $request->withHeader('Accept-Encoding', $filtered);
    }

    public function filterResponse(ResponseInterface $response): ResponseInterface
    {
        return $response;
    }

    private function removeBrotli(string $encoding): string
    {
        $encoding = preg_replace('/\bbr\b\s*,?\s*/', '', $encoding);

        return rtrim($encoding, ', ');
    }
}
