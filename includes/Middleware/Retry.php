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
    private $retries;

    /** @var string[] */
    private $methods;

    /** @var int[] */
    private $statusCodes;

    /**
     * @param int $retries Max retry attempts
     * @param string[] $methods Retryable HTTP methods (options: GET|HEAD|OPTIONS|PUT|DELETE)
     * @param int[] $statusCodes Status codes that trigger retry
     */
    public function __construct(
        int $retries = 3,
        array $methods = ['GET', 'HEAD', 'OPTIONS'],
        array $statusCodes = [502, 503, 504]
    ) {
        $this->retries = $retries;
        $this->methods = array_map('strtoupper', $methods);
        $this->statusCodes = $statusCodes;
    }

    public function process(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        $method = strtoupper($request->getMethod());
        $canRetry = Arr::contains($this->methods, $method);

        $attempts = 0;
        $lastException = null;
        $lastResponse = null;

        while ($attempts < $this->retries) {
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
                if (! $canRetry || $attempts >= $this->retries) {
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

        return Arr::contains($this->statusCodes, $response->getStatusCode());
    }
}
