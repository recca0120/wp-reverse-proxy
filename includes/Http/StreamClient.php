<?php

namespace Recca0120\ReverseProxy\Http;

use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Recca0120\ReverseProxy\Exceptions\NetworkException;
use Recca0120\ReverseProxy\Http\Concerns\ParsesResponse;

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

        $timeout = $this->options['timeout'] ?? 30;
        $connectTimeout = $this->options['connect_timeout'] ?? $timeout;

        $httpOptions = [
            'method' => $request->getMethod(),
            'header' => implode("\r\n", $this->prepareHeaders($request)),
            'content' => (string) $request->getBody(),
            'timeout' => min($timeout, $connectTimeout),
            'protocol_version' => (float) ($this->options['protocol_version'] ?? '1.1'),
            'follow_location' => 0,
            'ignore_errors' => true,
        ];

        if (isset($this->options['proxy'])) {
            $httpOptions['proxy'] = $this->options['proxy'];
            $httpOptions['request_fulluri'] = true;
        }

        $context = stream_context_create([
            'http' => $httpOptions,
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

        [$protocolVersion, $statusCode, $reasonPhrase, $headers] = $this->parseResponseHeaders($http_response_header);
        $body = $this->decodeBody($body, $headers);

        return new Response($statusCode, $headers, $body, $protocolVersion, $reasonPhrase);
    }

    private function decodeBody(string $body, array &$headers): string
    {
        if (! ($this->options['decode_content'] ?? true)) {
            return $body;
        }

        $encodingName = $this->findHeaderName($headers, 'Content-Encoding');

        if ($encodingName === null) {
            return $body;
        }

        $encoding = $headers[$encodingName][0] ?? null;
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
            unset($headers[$encodingName]);

            return $decoded;
        }

        return $body;
    }
}
