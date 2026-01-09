<?php

namespace Recca0120\ReverseProxy\Middleware;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Recca0120\ReverseProxy\Concerns\HasCache;
use Recca0120\ReverseProxy\Contracts\CacheAwareInterface;
use Recca0120\ReverseProxy\Contracts\MiddlewareInterface;

/**
 * Limit the number of requests.
 */
class RateLimiting implements MiddlewareInterface, CacheAwareInterface
{
    use HasCache;

    /** @var int */
    private $limit;

    /** @var int */
    private $window;

    /** @var callable|null */
    private $keyGenerator;

    /**
     * @param int $limit Max requests allowed
     * @param int $window Time window in seconds
     * @param callable|null $keyGenerator
     */
    public function __construct(
        int $limit = 100,
        int $window = 60,
        ?callable $keyGenerator = null
    ) {
        $this->limit = $limit;
        $this->window = $window;
        $this->keyGenerator = $keyGenerator;
    }

    public function process(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        $key = $this->generateKey($request);
        $data = $this->getRateLimitData($key);

        $now = time();
        $windowStart = $data['window_start'] ?? $now;
        $requestCount = $data['count'] ?? 0;

        // 如果時間窗口已過，重置計數
        if ($now - $windowStart >= $this->window) {
            $windowStart = $now;
            $requestCount = 0;
        }

        $requestCount++;
        $remaining = max(0, $this->limit - $requestCount);
        $resetTime = $windowStart + $this->window;

        // 儲存更新後的資料
        $this->saveRateLimitData($key, [
            'window_start' => $windowStart,
            'count' => $requestCount,
        ]);

        // 超過限制
        if ($requestCount > $this->limit) {
            return $this->createTooManyRequestsResponse($resetTime, $remaining);
        }

        // 執行請求並加入 headers
        $response = $next($request);

        return $this->addRateLimitHeaders($response, $remaining, $resetTime);
    }

    protected function getCachePrefix(): string
    {
        return 'rp_rate_';
    }

    private function generateKey(ServerRequestInterface $request): string
    {
        if ($this->keyGenerator !== null) {
            return ($this->keyGenerator)($request);
        }

        // 預設用 IP
        $serverParams = $request->getServerParams();

        return $serverParams['REMOTE_ADDR'] ?? 'unknown';
    }

    private function getRateLimitData(string $key): array
    {
        $cacheKey = md5($key);
        $data = $this->cacheGet($cacheKey);

        return is_array($data) ? $data : [];
    }

    private function saveRateLimitData(string $key, array $data): void
    {
        $cacheKey = md5($key);
        $this->cacheSet($cacheKey, $data, $this->window);
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
                'X-RateLimit-Limit' => (string) $this->limit,
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
            ->withHeader('X-RateLimit-Limit', (string) $this->limit)
            ->withHeader('X-RateLimit-Remaining', (string) $remaining)
            ->withHeader('X-RateLimit-Reset', (string) $resetTime);
    }
}
