<?php

namespace ReverseProxy\WordPress;

use Psr\Http\Message\ResponseInterface;

class ResponseEmitter
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

    public function emit(ResponseInterface $response): void
    {
        if (! headers_sent()) {
            http_response_code($response->getStatusCode());

            foreach ($this->getHeadersToEmit($response) as $name => $values) {
                foreach ($values as $value) {
                    header("{$name}: {$value}", false);
                }
            }
        }

        echo $response->getBody();
    }

    /**
     * @return array<string, string[]>
     */
    public function getHeadersToEmit(ResponseInterface $response): array
    {
        $headers = [];

        foreach ($response->getHeaders() as $name => $values) {
            if ($this->shouldFilter($name)) {
                continue;
            }

            $headers[$name] = $values;
        }

        return $headers;
    }

    private function shouldFilter(string $headerName): bool
    {
        return in_array(strtolower($headerName), self::FILTERED_HEADERS, true);
    }
}
