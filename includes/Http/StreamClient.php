<?php

namespace ReverseProxy\Http;

use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use ReverseProxy\Exceptions\NetworkException;
use ReverseProxy\Http\Concerns\ParsesResponse;

class StreamClient implements ClientInterface
{
    use ParsesResponse;

    /** @var array */
    private $options;

    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $verify = $this->options['verify'] ?? true;

        $context = stream_context_create([
            'http' => [
                'method' => $request->getMethod(),
                'header' => implode("\r\n", $this->prepareHeaders($request)),
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

        $http_response_header = [];

        $body = @file_get_contents((string) $request->getUri(), false, $context);

        if ($body === false) {
            $error = error_get_last();
            throw new NetworkException($error['message'] ?? 'Unknown error', $request);
        }

        $statusCode = $this->parseStatusCode($http_response_header);
        $headers = $this->parseResponseHeaders($http_response_header);
        $body = $this->decodeBody($body, $headers);

        return new Response($statusCode, $headers, $body);
    }

    private function decodeBody(string $body, array &$headers): string
    {
        if (! ($this->options['decode_content'] ?? true)) {
            return $body;
        }

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
