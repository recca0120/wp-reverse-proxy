<?php

namespace ReverseProxy\Http;

use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class CurlHttpClient implements ClientInterface
{
    /** @var array */
    private $options;

    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => (string) $request->getUri(),
            CURLOPT_CUSTOMREQUEST => $request->getMethod(),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => $this->prepareHeaders($request),
            CURLOPT_TIMEOUT => $this->options['timeout'] ?? 30,
            CURLOPT_SSL_VERIFYPEER => $this->options['verify'] ?? true,
        ]);

        $body = (string) $request->getBody();
        if ($body !== '') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        foreach ($this->options as $option => $value) {
            if (is_int($option)) {
                curl_setopt($ch, $option, $value);
            }
        }

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new NetworkException($error, $request);
        }

        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $headerString = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);

        return new Response($statusCode, $this->parseHeaders($headerString), $body);
    }

    private function prepareHeaders(RequestInterface $request): array
    {
        $headers = [];

        foreach ($request->getHeaders() as $name => $values) {
            $headers[] = $name.': '.implode(', ', $values);
        }

        return $headers;
    }

    private function parseHeaders(string $headerString): array
    {
        $headers = [];
        $lines = explode("\r\n", trim($headerString));

        foreach ($lines as $line) {
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
