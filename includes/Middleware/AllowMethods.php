<?php

namespace Recca0120\ReverseProxy\Middleware;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Recca0120\ReverseProxy\Contracts\MiddlewareInterface;
use Recca0120\ReverseProxy\Support\Arr;

/**
 * @UIDescription("Restrict allowed HTTP methods")
 */
class AllowMethods implements MiddlewareInterface
{
    /** @var string[] */
    private $allowedMethods;

    /**
     * @param  string|string[]  ...$allowedMethods
     *
     * @UIField(name="allowedMethods", type="checkboxes", label="Allowed Methods", options="GET,POST,PUT,PATCH,DELETE,HEAD,OPTIONS", default="GET")
     */
    public function __construct(...$allowedMethods)
    {
        // Support both: new AllowMethods('GET', 'POST') and new AllowMethods(['GET', 'POST'])
        if (count($allowedMethods) === 1 && is_array($allowedMethods[0])) {
            $allowedMethods = $allowedMethods[0];
        }
        $methods = $allowedMethods ?: ['GET'];
        $this->allowedMethods = array_map('strtoupper', $methods);
    }

    public function process(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        $method = strtoupper($request->getMethod());

        // Always allow OPTIONS for CORS preflight
        if ($method === 'OPTIONS') {
            return $next($request);
        }

        if (! Arr::contains($this->allowedMethods, $method)) {
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
