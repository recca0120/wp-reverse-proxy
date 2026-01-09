<?php

namespace Recca0120\ReverseProxy\Middleware;

use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Recca0120\ReverseProxy\Concerns\HasCache;
use Recca0120\ReverseProxy\Contracts\CacheAwareInterface;
use Recca0120\ReverseProxy\Contracts\MiddlewareInterface;
use Recca0120\ReverseProxy\Support\Arr;

/**
 * Prevent cascading failures.
 */
class CircuitBreaker implements MiddlewareInterface, CacheAwareInterface
{
    use HasCache;

    public const STATE_CLOSED = 'closed';

    public const STATE_OPEN = 'open';

    public const STATE_HALF_OPEN = 'half_open';

    /** @var int */
    public $priority = -70;

    /** @var string */
    private $serviceName;

    /** @var int */
    private $failureThreshold;

    /** @var int */
    private $resetTimeout;

    /** @var int[] */
    private $failureStatusCodes;

    /**
     * @param string $serviceName Service Name
     * @param int $failureThreshold Failure Threshold
     * @param int $resetTimeout Reset Timeout (seconds)
     * @param int[] $failureStatusCodes Failure Status Codes
     */
    public function __construct(
        string $serviceName,
        int $failureThreshold = 5,
        int $resetTimeout = 60,
        array $failureStatusCodes = [500, 502, 503, 504]
    ) {
        $this->serviceName = $serviceName;
        $this->failureThreshold = $failureThreshold;
        $this->resetTimeout = $resetTimeout;
        $this->failureStatusCodes = $failureStatusCodes;
    }

    public function process(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        $state = $this->getState();

        // Circuit 開啟時，快速失敗
        if ($state['status'] === self::STATE_OPEN) {
            // 檢查是否可以進入 half-open
            if (time() >= $state['reset_at']) {
                $this->setState(self::STATE_HALF_OPEN, 0);
            } else {
                return $this->createCircuitOpenResponse();
            }
        }

        try {
            $response = $next($request);

            if ($this->isFailure($response)) {
                $this->recordFailure();
            } else {
                $this->recordSuccess();
            }

            return $response;
        } catch (ClientExceptionInterface $e) {
            $this->recordFailure();
            throw $e;
        }
    }

    protected function getCachePrefix(): string
    {
        return 'rp_cb_';
    }

    private function getState(): array
    {
        $key = $this->getCacheKey();
        $data = $this->cacheGet($key);

        if ($data === null || ! is_array($data)) {
            return [
                'status' => self::STATE_CLOSED,
                'failures' => 0,
                'reset_at' => 0,
            ];
        }

        return $data;
    }

    private function setState(string $status, int $failures): void
    {
        $key = $this->getCacheKey();
        $resetAt = $status === self::STATE_OPEN ? time() + $this->resetTimeout : 0;

        $this->cacheSet($key, [
            'status' => $status,
            'failures' => $failures,
            'reset_at' => $resetAt,
        ], $this->resetTimeout * 2);
    }

    private function recordFailure(): void
    {
        $state = $this->getState();
        $failures = $state['failures'] + 1;

        if ($failures >= $this->failureThreshold) {
            $this->setState(self::STATE_OPEN, $failures);
        } else {
            $this->setState(self::STATE_CLOSED, $failures);
        }
    }

    private function recordSuccess(): void
    {
        $this->setState(self::STATE_CLOSED, 0);
    }

    private function isFailure(ResponseInterface $response): bool
    {
        return Arr::contains($this->failureStatusCodes, $response->getStatusCode());
    }

    private function getCacheKey(): string
    {
        return md5($this->serviceName);
    }

    private function createCircuitOpenResponse(): ResponseInterface
    {
        $body = json_encode([
            'error' => 'Circuit breaker is open',
            'service' => $this->serviceName,
            'status' => 503,
        ]);

        return new Response(
            503,
            ['Content-Type' => 'application/json'],
            $body
        );
    }
}
