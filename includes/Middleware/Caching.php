<?php

namespace Recca0120\ReverseProxy\Middleware;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Recca0120\ReverseProxy\Concerns\HasCache;
use Recca0120\ReverseProxy\Contracts\CacheAwareInterface;
use Recca0120\ReverseProxy\Contracts\MiddlewareInterface;
use Recca0120\ReverseProxy\Support\Arr;
use Recca0120\ReverseProxy\Support\Str;

/**
 * Cache responses.
 */
class Caching implements MiddlewareInterface, CacheAwareInterface
{
    use HasCache;

    /** @var int */
    private $ttl;

    /**
     * @param int $ttl TTL (seconds)
     */
    public function __construct(int $ttl = 300)
    {
        $this->ttl = $ttl;
    }

    public function process(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        // 只快取 GET 和 HEAD 請求
        $method = strtoupper($request->getMethod());
        if (! Arr::contains(['GET', 'HEAD'], $method)) {
            return $next($request);
        }

        $cacheKey = $this->generateCacheKey($request);

        // 嘗試從快取取得
        $cached = $this->cacheGet($cacheKey);
        if ($cached !== null && is_array($cached)) {
            return $this->restoreResponse($cached)->withHeader('X-Cache', 'HIT');
        }

        // 執行請求
        $response = $next($request);

        // 只快取成功的回應且沒有 no-cache 指令
        if ($this->isCacheable($response)) {
            $this->cacheResponse($cacheKey, $response);
        }

        return $response->withHeader('X-Cache', 'MISS');
    }

    protected function getCachePrefix(): string
    {
        return 'rp_cache_';
    }

    private function generateCacheKey(ServerRequestInterface $request): string
    {
        return md5((string) $request->getUri());
    }

    private function isCacheable(ResponseInterface $response): bool
    {
        if ($response->getStatusCode() !== 200) {
            return false;
        }

        return ! $this->hasNoCacheDirective($response->getHeaderLine('Cache-Control'));
    }

    private function hasNoCacheDirective(string $cacheControl): bool
    {
        $cacheControl = strtolower($cacheControl);

        foreach (['no-cache', 'no-store', 'private'] as $directive) {
            if (Str::contains($cacheControl, $directive)) {
                return true;
            }
        }

        return false;
    }

    private function cacheResponse(string $key, ResponseInterface $response): void
    {
        $data = [
            'status' => $response->getStatusCode(),
            'headers' => $response->getHeaders(),
            'body' => (string) $response->getBody(),
            'protocol' => $response->getProtocolVersion(),
            'reason' => $response->getReasonPhrase(),
        ];

        $this->cacheSet($key, $data, $this->ttl);
    }

    private function restoreResponse(array $data): ResponseInterface
    {
        return new Response(
            $data['status'],
            $data['headers'],
            $data['body'],
            $data['protocol'] ?? '1.1',
            $data['reason'] ?? ''
        );
    }
}
