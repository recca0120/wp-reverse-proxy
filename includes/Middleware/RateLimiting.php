<?php

namespace ReverseProxy\Middleware;

use Nyholm\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use ReverseProxy\Contracts\MiddlewareInterface;

class RateLimiting implements MiddlewareInterface
{
    /** @var int */
    private $maxRequests;

    /** @var int */
    private $windowSeconds;

    /** @var callable|null */
    private $keyGenerator;

    /**
     * @param  int  $maxRequests  每個時間窗口的最大請求數
     * @param  int  $windowSeconds  時間窗口（秒）
     * @param  callable|null  $keyGenerator  自訂 key 產生器
     */
    public function __construct(int $maxRequests, int $windowSeconds, ?callable $keyGenerator = null)
    {
        $this->maxRequests = $maxRequests;
        $this->windowSeconds = $windowSeconds;
        $this->keyGenerator = $keyGenerator;
    }

    public function process(RequestInterface $request, callable $next): ResponseInterface
    {
        $key = $this->generateKey($request);
        $data = $this->getRateLimitData($key);

        $now = time();
        $windowStart = $data['window_start'] ?? $now;
        $requestCount = $data['count'] ?? 0;

        // 如果時間窗口已過，重置計數
        if ($now - $windowStart >= $this->windowSeconds) {
            $windowStart = $now;
            $requestCount = 0;
        }

        $requestCount++;
        $remaining = max(0, $this->maxRequests - $requestCount);
        $resetTime = $windowStart + $this->windowSeconds;

        // 儲存更新後的資料
        $this->saveRateLimitData($key, [
            'window_start' => $windowStart,
            'count' => $requestCount,
        ]);

        // 超過限制
        if ($requestCount > $this->maxRequests) {
            return $this->createTooManyRequestsResponse($resetTime, $remaining);
        }

        // 執行請求並加入 headers
        $response = $next($request);

        return $this->addRateLimitHeaders($response, $remaining, $resetTime);
    }

    private function generateKey(RequestInterface $request): string
    {
        if ($this->keyGenerator !== null) {
            return ($this->keyGenerator)($request);
        }

        // 預設用 IP
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    private function getRateLimitData(string $key): array
    {
        $cacheKey = 'rp_rate_'.md5($key);
        $data = get_transient($cacheKey);

        return is_array($data) ? $data : [];
    }

    private function saveRateLimitData(string $key, array $data): void
    {
        $cacheKey = 'rp_rate_'.md5($key);
        set_transient($cacheKey, $data, $this->windowSeconds);
    }

    private function createTooManyRequestsResponse(int $resetTime, int $remaining): ResponseInterface
    {
        $retryAfter = max(1, $resetTime - time());

        $body = json_encode([
            'error' => 'Too Many Requests',
            'status' => 429,
            'retry_after' => $retryAfter,
        ]);

        return new Response(
            429,
            [
                'Content-Type' => 'application/json',
                'X-RateLimit-Limit' => (string) $this->maxRequests,
                'X-RateLimit-Remaining' => (string) $remaining,
                'X-RateLimit-Reset' => (string) $resetTime,
                'Retry-After' => (string) $retryAfter,
            ],
            $body
        );
    }

    private function addRateLimitHeaders(ResponseInterface $response, int $remaining, int $resetTime): ResponseInterface
    {
        return $response
            ->withHeader('X-RateLimit-Limit', (string) $this->maxRequests)
            ->withHeader('X-RateLimit-Remaining', (string) $remaining)
            ->withHeader('X-RateLimit-Reset', (string) $resetTime);
    }
}
