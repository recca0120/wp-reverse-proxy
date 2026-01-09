<?php

namespace Recca0120\ReverseProxy\Middleware;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Recca0120\ReverseProxy\Contracts\MiddlewareInterface;
use Recca0120\ReverseProxy\Support\Arr;

/**
 * Retry failed requests.
 */
class Retry implements MiddlewareInterface
{
    /** @var int */
    public $priority = -80;

    /** @var int */
    private $maxRetries;

    /** @var string[] */
    private $retryableMethods;

    /** @var int[] */
    private $retryableStatusCodes;

    /**
     * @param int $maxRetries Max Retries
     * @param string[] $retryableMethods Retryable Methods (options: GET|HEAD|OPTIONS|PUT|DELETE)
     * @param int[] $retryableStatusCodes Retryable Status Codes
     */
    public function __construct(
        int $maxRetries = 3,
        array $retryableMethods = ['GET', 'HEAD', 'OPTIONS'],
        array $retryableStatusCodes = [502, 503, 504]
    ) {
        $this->maxRetries = $maxRetries;
        $this->retryableMethods = array_map('strtoupper', $retryableMethods);
        $this->retryableStatusCodes = $retryableStatusCodes;
    }

    public function process(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        $method = strtoupper($request->getMethod());
        $canRetry = Arr::contains($this->retryableMethods, $method);

        $attempts = 0;
        $lastException = null;
        $lastResponse = null;

        while ($attempts < $this->maxRetries) {
            $attempts++;

            try {
                $response = $next($request);

                // 如果成功或不可重試，直接返回
                if (! $this->shouldRetry($response, $canRetry)) {
                    return $response;
                }

                $lastResponse = $response;
            } catch (ClientExceptionInterface $e) {
                // 網路錯誤，可重試
                if (! $canRetry || $attempts >= $this->maxRetries) {
                    throw $e;
                }

                $lastException = $e;
            }
        }

        // 重試次數用盡
        if ($lastResponse !== null) {
            return $lastResponse;
        }

        throw $lastException;
    }

    private function shouldRetry(ResponseInterface $response, bool $canRetry): bool
    {
        if (! $canRetry) {
            return false;
        }

        return Arr::contains($this->retryableStatusCodes, $response->getStatusCode());
    }
}
