<?php

namespace ReverseProxy\Middleware;

use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use ReverseProxy\MiddlewareInterface;

class ErrorHandlingMiddleware implements MiddlewareInterface
{
    /** @var int */
    public $priority = -100;

    public function process(RequestInterface $request, callable $next): ResponseInterface
    {
        try {
            return $next($request);
        } catch (ClientExceptionInterface $e) {
            return $this->createErrorResponse(502, 'Bad Gateway: ' . $e->getMessage());
        }
    }

    private function createErrorResponse(int $statusCode, string $message): ResponseInterface
    {
        $body = json_encode([
            'error' => $message,
            'status' => $statusCode,
        ]);

        return new Response(
            $statusCode,
            ['Content-Type' => 'application/json'],
            $body
        );
    }
}
