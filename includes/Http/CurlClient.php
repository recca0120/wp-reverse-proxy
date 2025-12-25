<?php

namespace ReverseProxy\Http;

use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use ReverseProxy\Exceptions\NetworkException;
use ReverseProxy\Http\Concerns\ParsesHeaders;

class CurlClient implements ClientInterface
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
        $ch = curl_init();

        $verify = $this->options['verify'] ?? true;
        $decodeContent = $this->options['decode_content'] ?? true;

        $curlOptions = [
            CURLOPT_URL => (string) $request->getUri(),
            CURLOPT_CUSTOMREQUEST => $request->getMethod(),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => $this->formatHeaderLines($request),
            CURLOPT_TIMEOUT => $this->options['timeout'] ?? 30,
            CURLOPT_SSL_VERIFYPEER => $verify,
            CURLOPT_SSL_VERIFYHOST => $verify ? 2 : 0,
        ];

        if ($decodeContent) {
            $curlOptions[CURLOPT_ENCODING] = '';
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
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $headerString = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        $headers = $this->parseResponseHeaders(explode("\r\n", trim($headerString)));

        if ($decodeContent) {
            unset($headers['Content-Encoding']);
        }

        return new Response($statusCode, $headers, $body);
    }
}
