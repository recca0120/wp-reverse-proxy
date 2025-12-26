<?php

namespace ReverseProxy\Middleware;

use Nyholm\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\SimpleCache\CacheInterface;
use ReverseProxy\Contracts\MiddlewareInterface;
use ReverseProxy\WordPress\TransientCache;

class Caching implements MiddlewareInterface
{
    /** @var int */
    private $ttl;

    /** @var CacheInterface */
    private $cache;

    /**
     * @param  int  $ttl  快取時間（秒）
     * @param  CacheInterface|null  $cache  快取實作
     */
    public function __construct(int $ttl = 300, ?CacheInterface $cache = null)
    {
        $this->ttl = $ttl;
        $this->cache = $cache ?? new TransientCache('rp_cache_');
    }

    public function process(RequestInterface $request, callable $next): ResponseInterface
    {
        // 只快取 GET 和 HEAD 請求
        $method = strtoupper($request->getMethod());
        if (! in_array($method, ['GET', 'HEAD'], true)) {
            return $next($request);
        }

        $cacheKey = $this->generateCacheKey($request);

        // 嘗試從快取取得
        $cached = $this->cache->get($cacheKey);
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

    private function generateCacheKey(RequestInterface $request): string
    {
        return md5((string) $request->getUri());
    }

    private function isCacheable(ResponseInterface $response): bool
    {
        $statusCode = $response->getStatusCode();

        // 只快取 200 OK
        if ($statusCode !== 200) {
            return false;
        }

        // 檢查 Cache-Control header
        $cacheControl = $response->getHeaderLine('Cache-Control');
        if (stripos($cacheControl, 'no-cache') !== false
            || stripos($cacheControl, 'no-store') !== false
            || stripos($cacheControl, 'private') !== false
        ) {
            return false;
        }

        return true;
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

        $this->cache->set($key, $data, $this->ttl);
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
