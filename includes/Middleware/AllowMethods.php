<?php

namespace Recca0120\ReverseProxy\Middleware;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Recca0120\ReverseProxy\Contracts\MiddlewareInterface;
use Recca0120\ReverseProxy\Support\Arr;

/**
 * Restrict allowed HTTP methods.
 */
class AllowMethods implements MiddlewareInterface
{
    /** @var string[] */
    private $methods;

    /**
     * @param string|string[] $methods Allowed HTTP methods (options: GET|POST|PUT|PATCH|DELETE|HEAD|OPTIONS)
     */
    public function __construct(...$methods)
    {
        $input = Arr::wrap($methods) ?: ['GET'];
        $this->methods = array_map('strtoupper', $input);
    }

    public function process(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        $method = strtoupper($request->getMethod());

        // Always allow OPTIONS for CORS preflight
        if ($method === 'OPTIONS') {
            return $next($request);
        }

        if (! Arr::contains($this->methods, $method)) {
            return $this->createMethodNotAllowedResponse();
        }

        return $next($request);
    }

    private function createMethodNotAllowedResponse(): ResponseInterface
    {
        $body = json_encode([
            'error' => 'Method Not Allowed',
            'status' => 405,
        ]);

        return new Response(
            405,
            [
                'Content-Type' => 'application/json',
                'Allow' => implode(', ', $this->methods),
            ],
            $body
        );
    }
}
