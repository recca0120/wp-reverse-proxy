<?php

namespace ReverseProxy\Http;

use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class StreamHttpClient implements ClientInterface
{
    /** @var array */
    private $options;

    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $context = stream_context_create([
            'http' => [
                'method' => $request->getMethod(),
                'header' => $this->prepareHeaders($request),
                'content' => (string) $request->getBody(),
                'timeout' => $this->options['timeout'] ?? 30,
                'follow_location' => 0,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => $this->options['verify'] ?? true,
                'verify_peer_name' => $this->options['verify'] ?? true,
            ],
        ]);

        $body = @file_get_contents((string) $request->getUri(), false, $context);

        if ($body === false) {
            $error = error_get_last();
            throw new NetworkException($error['message'] ?? 'Unknown error', $request);
        }

        // $http_response_header is a magic variable set by file_get_contents
        global $http_response_header;

        $statusCode = 200;
        $headers = [];

        if (isset($http_response_header) && is_array($http_response_header)) {
            $headers = $this->parseHeaders($http_response_header, $statusCode);
        }

        return new Response($statusCode, $headers, $body);
    }

    private function prepareHeaders(RequestInterface $request): string
    {
        $headers = [];

        foreach ($request->getHeaders() as $name => $values) {
            $headers[] = $name.': '.implode(', ', $values);
        }

        return implode("\r\n", $headers);
    }

    /**
     * @param  string[]  $headerLines
     */
    private function parseHeaders(array $headerLines, int &$statusCode): array
    {
        $headers = [];

        foreach ($headerLines as $line) {
            if (preg_match('/^HTTP\/[\d.]+ (\d+)/', $line, $matches)) {
                $statusCode = (int) $matches[1];

                continue;
            }

            if (strpos($line, ':') === false) {
                continue;
            }

            [$name, $value] = explode(':', $line, 2);
            $name = trim($name);
            $value = trim($value);

            if (isset($headers[$name])) {
                $headers[$name][] = $value;
            } else {
                $headers[$name] = [$value];
            }
        }

        return $headers;
    }
}
