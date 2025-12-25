<?php

namespace ReverseProxy\Http;

use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use ReverseProxy\Exceptions\NetworkException;
use ReverseProxy\Http\Concerns\ParsesHeaders;

class StreamClient implements ClientInterface
{
    use ParsesHeaders;

    /** @var array */
    private $options;

    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $verify = $this->options['verify'] ?? true;
        $decodeContent = $this->options['decode_content'] ?? true;

        $context = stream_context_create([
            'http' => [
                'method' => $request->getMethod(),
                'header' => implode("\r\n", $this->formatHeaderLines($request)),
                'content' => (string) $request->getBody(),
                'timeout' => $this->options['timeout'] ?? 30,
                'follow_location' => 0,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => $verify,
                'verify_peer_name' => $verify,
            ],
        ]);

        // $http_response_header is a magic variable set by file_get_contents in local scope
        $http_response_header = [];

        $body = @file_get_contents((string) $request->getUri(), false, $context);

        if ($body === false) {
            $error = error_get_last();
            throw new NetworkException($error['message'] ?? 'Unknown error', $request);
        }

        $statusCode = 200;
        $headers = [];

        if (! empty($http_response_header)) {
            $headers = $this->parseResponseHeaders($http_response_header, $statusCode);
        }

        if ($decodeContent) {
            $body = $this->decodeBody($body, $headers);
        }

        return new Response($statusCode, $headers, $body);
    }

    /**
     * Decode response body based on Content-Encoding header.
     */
    private function decodeBody(string $body, array &$headers): string
    {
        $encoding = $headers['Content-Encoding'][0] ?? null;

        if ($encoding === null) {
            return $body;
        }

        $decoded = null;

        switch (strtolower($encoding)) {
            case 'gzip':
                $decoded = @gzdecode($body);
                break;
            case 'deflate':
                $decoded = @gzinflate($body);
                break;
        }

        if ($decoded !== false && $decoded !== null) {
            unset($headers['Content-Encoding']);

            return $decoded;
        }

        return $body;
    }
}
