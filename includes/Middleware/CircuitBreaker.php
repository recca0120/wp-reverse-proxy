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
    private $threshold;

    /** @var int */
    private $timeout;

    /** @var int[] */
    private $statusCodes;

    /**
     * @param string $serviceName Service name for circuit identification
     * @param int $threshold Failure count to open circuit
     * @param int $timeout Seconds before attempting recovery
     * @param int[] $statusCodes Status codes considered as failures
     */
    public function __construct(
        string $serviceName,
        int $threshold = 5,
        int $timeout = 60,
        array $statusCodes = [500, 502, 503, 504]
    ) {
        $this->serviceName = $serviceName;
        $this->threshold = $threshold;
        $this->timeout = $timeout;
        $this->statusCodes = $statusCodes;
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
        $resetAt = $status === self::STATE_OPEN ? time() + $this->timeout : 0;

        $this->cacheSet($key, [
            'status' => $status,
            'failures' => $failures,
            'reset_at' => $resetAt,
        ], $this->timeout * 2);
    }

    private function recordFailure(): void
    {
        $state = $this->getState();
        $failures = $state['failures'] + 1;

        if ($failures >= $this->threshold) {
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
        return Arr::contains($this->statusCodes, $response->getStatusCode());
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
