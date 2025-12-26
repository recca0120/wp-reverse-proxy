<?php

namespace ReverseProxy\Middleware;

use Nyholm\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use ReverseProxy\Contracts\MiddlewareInterface;

class Caching implements MiddlewareInterface
{
    /** @var int */
    private $ttl;

    /** @var string */
    private $cacheGroup;

    /**
     * @param  int  $ttl  快取時間（秒）
     * @param  string  $cacheGroup  快取群組
     */
    public function __construct(int $ttl = 300, string $cacheGroup = 'reverse_proxy')
    {
        $this->ttl = $ttl;
        $this->cacheGroup = $cacheGroup;
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
        $cached = get_transient($cacheKey);
        if ($cached !== false && is_array($cached)) {
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
        return 'rp_cache_'.md5((string) $request->getUri());
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

        set_transient($key, $data, $this->ttl);
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
