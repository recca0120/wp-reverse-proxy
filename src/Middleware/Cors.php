<?php

namespace Recca0120\ReverseProxy\Middleware;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Recca0120\ReverseProxy\Contracts\MiddlewareInterface;
use Recca0120\ReverseProxy\Support\Arr;

/**
 * Add CORS headers to the response.
 */
class Cors implements MiddlewareInterface
{
    /** @var string[] */
    private $origins;

    /** @var string[] */
    private $methods;

    /** @var string[] */
    private $headers;

    /** @var bool */
    private $credentials;

    /** @var int */
    private $maxAge;

    /**
     * @param string[] $origins Allowed origins
     * @param string[] $methods Allowed HTTP methods (options: GET|POST|PUT|PATCH|DELETE|OPTIONS)
     * @param string[] $headers Allowed request headers
     * @param bool $credentials Allow credentials (cookies)
     * @param int $maxAge Preflight cache time in seconds
     */
    public function __construct(
        array $origins = ['*'],
        array $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        array $headers = ['Content-Type', 'Authorization', 'X-Requested-With'],
        bool $credentials = false,
        int $maxAge = 0
    ) {
        $this->origins = $origins;
        $this->methods = $methods;
        $this->headers = $headers;
        $this->credentials = $credentials;
        $this->maxAge = $maxAge;
    }

    public function process(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        $origin = $request->getHeaderLine('Origin');

        // 沒有 Origin header，不是 CORS 請求
        if ($origin === '') {
            return $next($request);
        }

        // 檢查 origin 是否被允許
        $allowedOrigin = $this->resolveAllowedOrigin($origin);

        // Preflight OPTIONS 請求
        if ($this->isPreflight($request)) {
            return $this->handlePreflight($allowedOrigin);
        }

        // 一般請求，加入 CORS headers
        $response = $next($request);

        return $this->addCorsHeaders($response, $allowedOrigin);
    }

    private function resolveAllowedOrigin(string $origin): string
    {
        if (Arr::contains($this->origins, '*')) {
            return '*';
        }

        if (Arr::contains($this->origins, $origin)) {
            return $origin;
        }

        return '';
    }

    private function isPreflight(ServerRequestInterface $request): bool
    {
        return strtoupper($request->getMethod()) === 'OPTIONS'
            && $request->hasHeader('Access-Control-Request-Method');
    }

    private function handlePreflight(string $allowedOrigin): ResponseInterface
    {
        $response = new Response(204);

        if ($allowedOrigin === '') {
            return $response;
        }

        $response = $response
            ->withHeader('Access-Control-Allow-Origin', $allowedOrigin)
            ->withHeader('Access-Control-Allow-Methods', implode(', ', $this->methods))
            ->withHeader('Access-Control-Allow-Headers', implode(', ', $this->headers))
            ->withHeader('Vary', 'Origin');

        if ($this->credentials) {
            $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        if ($this->maxAge > 0) {
            $response = $response->withHeader('Access-Control-Max-Age', (string) $this->maxAge);
        }

        return $response;
    }

    private function addCorsHeaders(ResponseInterface $response, string $allowedOrigin): ResponseInterface
    {
        if ($allowedOrigin === '') {
            return $response;
        }

        $response = $response
            ->withHeader('Access-Control-Allow-Origin', $allowedOrigin)
            ->withHeader('Vary', 'Origin');

        if ($this->credentials) {
            $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }
}
