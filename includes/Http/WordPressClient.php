<?php

namespace ReverseProxy\Http;

use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use ReverseProxy\Exceptions\NetworkException;

class WordPressClient implements ClientInterface
{
    /** @var array */
    private $options;

    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $args = array_merge([
            'method' => $request->getMethod(),
            'headers' => $this->prepareHeaders($request),
            'body' => (string) $request->getBody(),
            'timeout' => 30,
            'redirection' => 0,
            'decompress' => false,
        ], $this->options);

        $response = wp_remote_request((string) $request->getUri(), $args);

        if (is_wp_error($response)) {
            throw new NetworkException($response->get_error_message(), $request);
        }

        return new Response(
            wp_remote_retrieve_response_code($response),
            wp_remote_retrieve_headers($response)->getAll(),
            wp_remote_retrieve_body($response)
        );
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
