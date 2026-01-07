<?php

namespace Recca0120\ReverseProxy\Http;

use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Recca0120\ReverseProxy\Exceptions\NetworkException;
use Recca0120\ReverseProxy\Http\Concerns\ParsesResponse;

class CurlClient implements ClientInterface
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
        $ch = curl_init();

        $verify = $this->options['verify'] ?? true;

        $curlOptions = [
            CURLOPT_URL => (string) $request->getUri(),
            CURLOPT_CUSTOMREQUEST => $request->getMethod(),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => $this->prepareHeaders($request),
            CURLOPT_TIMEOUT => $this->options['timeout'] ?? 30,
            CURLOPT_CONNECTTIMEOUT => $this->options['connect_timeout'] ?? ($this->options['timeout'] ?? 30),
            CURLOPT_SSL_VERIFYPEER => $verify,
            CURLOPT_SSL_VERIFYHOST => $verify ? 2 : 0,
        ];

        if ($this->options['decode_content'] ?? true) {
            $curlOptions[CURLOPT_ENCODING] = '';
        }

        if (isset($this->options['proxy'])) {
            $curlOptions[CURLOPT_PROXY] = $this->options['proxy'];
        }

        curl_setopt_array($ch, $curlOptions);

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
        curl_close($ch);

        $body = substr($response, $headerSize);
        $headerLines = explode("\r\n", trim(substr($response, 0, $headerSize)));
        [$protocolVersion, $statusCode, $reasonPhrase, $headers] = $this->parseResponseHeaders($headerLines);
        $body = $this->decodeBody($body, $headers);

        return new Response($statusCode, $headers, $body, $protocolVersion, $reasonPhrase);
    }

    private function decodeBody(string $body, array &$headers): string
    {
        if (! ($this->options['decode_content'] ?? true)) {
            return $body;
        }

        $encodingName = $this->findHeaderName($headers, 'Content-Encoding');

        if ($encodingName !== null) {
            unset($headers[$encodingName]);
        }

        return $body;
    }
}
