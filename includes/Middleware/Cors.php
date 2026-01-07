<?php

namespace Recca0120\ReverseProxy\Middleware;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Recca0120\ReverseProxy\Contracts\MiddlewareInterface;

class Cors implements MiddlewareInterface
{
    /** @var string[] */
    private $allowedOrigins;

    /** @var string[] */
    private $allowedMethods;

    /** @var string[] */
    private $allowedHeaders;

    /** @var bool */
    private $allowCredentials;

    /** @var int */
    private $maxAge;

    /**
     * @param  string[]  $allowedOrigins
     * @param  string[]  $allowedMethods
     * @param  string[]  $allowedHeaders
     */
    public function __construct(
        array $allowedOrigins = ['*'],
        array $allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        array $allowedHeaders = ['Content-Type', 'Authorization', 'X-Requested-With'],
        bool $allowCredentials = false,
        int $maxAge = 0
    ) {
        $this->allowedOrigins = $allowedOrigins;
        $this->allowedMethods = $allowedMethods;
        $this->allowedHeaders = $allowedHeaders;
        $this->allowCredentials = $allowCredentials;
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
        if (in_array('*', $this->allowedOrigins, true)) {
            return '*';
        }

        if (in_array($origin, $this->allowedOrigins, true)) {
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
            ->withHeader('Access-Control-Allow-Methods', implode(', ', $this->allowedMethods))
            ->withHeader('Access-Control-Allow-Headers', implode(', ', $this->allowedHeaders))
            ->withHeader('Vary', 'Origin');

        if ($this->allowCredentials) {
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

        if ($this->allowCredentials) {
            $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }
}
