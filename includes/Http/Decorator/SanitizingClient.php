<?php

namespace Recca0120\ReverseProxy\Http\Decorator;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class SanitizingClient implements ClientInterface
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

    /** @var ClientInterface */
    private $client;

    public function __construct(ClientInterface $client)
    {
        $this->client = $client;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $request = $this->removeBrotliEncoding($request);
        $response = $this->client->sendRequest($request);

        return $this->removeHopByHopHeaders($response);
    }

    private function removeBrotliEncoding(RequestInterface $request): RequestInterface
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
