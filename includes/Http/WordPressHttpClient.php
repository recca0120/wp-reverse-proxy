<?php

namespace ReverseProxy\Http;

use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class WordPressHttpClient implements ClientInterface
{
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $url = (string) $request->getUri();
        $method = $request->getMethod();
        $headers = $this->prepareHeaders($request);
        $body = (string) $request->getBody();

        $args = [
            'method' => $method,
            'headers' => $headers,
            'body' => $body,
            'timeout' => 30,
            'redirection' => 5,
            'sslverify' => true,
        ];

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            throw new NetworkException($response->get_error_message(), $request);
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $responseHeaders = wp_remote_retrieve_headers($response);
        $responseBody = wp_remote_retrieve_body($response);

        // Handle both array and Requests_Utility_CaseInsensitiveDictionary
        if (is_object($responseHeaders) && method_exists($responseHeaders, 'getAll')) {
            $headers = $responseHeaders->getAll();
        } else {
            $headers = (array) $responseHeaders;
        }

        return new Response($statusCode, $headers, $responseBody);
    }

    private function prepareHeaders(RequestInterface $request): array
    {
        $headers = [];

        foreach ($request->getHeaders() as $name => $values) {
            $headers[$name] = implode(', ', $values);
        }

        return $headers;
    }
}
