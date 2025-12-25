<?php

namespace ReverseProxy\Http;

use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
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
        $uri = $request->getUri();
        $url = (string) $uri;
        $verify = $this->options['verify'] ?? false;
        $sslOptions = [
            'verify_peer' => $verify,
            'verify_peer_name' => $verify,
        ];

        // Handle resolve option: map hostname to specific IP
        if (isset($this->options['resolve'])) {
            $resolved = $this->resolveHost($uri, $this->options['resolve']);
            if ($resolved !== null) {
                $url = $resolved['url'];
                $sslOptions['peer_name'] = $resolved['peer_name'];
            }
        }

        $context = stream_context_create([
            'http' => [
                'method' => $request->getMethod(),
                'header' => implode("\r\n", $this->formatHeaderLines($request)),
                'content' => (string) $request->getBody(),
                'timeout' => $this->options['timeout'] ?? 30,
                'follow_location' => 0,
                'ignore_errors' => true,
            ],
            'ssl' => $sslOptions,
        ]);

        // $http_response_header is a magic variable set by file_get_contents in local scope
        $http_response_header = [];

        $body = @file_get_contents($url, false, $context);

        if ($body === false) {
            $error = error_get_last();
            throw new NetworkException($error['message'] ?? 'Unknown error', $request);
        }

        $statusCode = 200;
        $headers = [];

        if (! empty($http_response_header)) {
            $headers = $this->parseResponseHeaders($http_response_header, $statusCode);
        }

        return new Response($statusCode, $headers, $body);
    }

    /**
     * Resolve hostname to IP based on resolve option.
     *
     * @param  \Psr\Http\Message\UriInterface  $uri
     * @param  string[]  $resolveList  Format: ['hostname:port:ip', ...]
     * @return array{url: string, peer_name: string}|null
     */
    private function resolveHost($uri, array $resolveList): ?array
    {
        $host = $uri->getHost();
        $port = $uri->getPort() ?? ($uri->getScheme() === 'https' ? 443 : 80);

        foreach ($resolveList as $entry) {
            $parts = explode(':', $entry);
            if (count($parts) !== 3) {
                continue;
            }

            [$resolveHost, $resolvePort, $resolveIp] = $parts;

            if ($host === $resolveHost && (int) $resolvePort === $port) {
                // Replace hostname with IP in URL
                $newUrl = str_replace("://{$host}", "://{$resolveIp}", (string) $uri);

                return [
                    'url' => $newUrl,
                    'peer_name' => $host,
                ];
            }
        }

        return null;
    }

    /**
     * @param  string[]  $headerLines
     */
    private function parseResponseHeaders(array $headerLines, int &$statusCode): array
    {
        $headers = [];

        foreach ($headerLines as $line) {
            if (preg_match('/^HTTP\/[\d.]+ (\d+)/', $line, $matches)) {
                $statusCode = (int) $matches[1];

                continue;
            }

            $parsed = $this->parseHeaderLine($line);
            if ($parsed !== null) {
                $this->addHeader($headers, $parsed[0], $parsed[1]);
            }
        }

        return $headers;
    }
}
