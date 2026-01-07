<?php

namespace Recca0120\ReverseProxy\Middleware;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Recca0120\ReverseProxy\Contracts\MiddlewareInterface;

class AllowMethods implements MiddlewareInterface
{
    /** @var string[] */
    private $allowedMethods;

    /**
     * @param  string[]  $allowedMethods
     */
    public function __construct(array $allowedMethods)
    {
        $this->allowedMethods = array_map('strtoupper', $allowedMethods);
    }

    public function process(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        $method = strtoupper($request->getMethod());

        // Always allow OPTIONS for CORS preflight
        if ($method === 'OPTIONS') {
            return $next($request);
        }

        if (! in_array($method, $this->allowedMethods, true)) {
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
                'Allow' => implode(', ', $this->allowedMethods),
            ],
            $body
        );
    }
}
