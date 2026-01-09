<?php

namespace Recca0120\ReverseProxy\Middleware;

use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Recca0120\ReverseProxy\Contracts\MiddlewareInterface;
use Recca0120\ReverseProxy\Support\Str;

/**
 * Set request timeout.
 */
class Timeout implements MiddlewareInterface
{
    /** @var int */
    public $priority = -60;

    /** @var int */
    private $seconds;

    /**
     * @param int $seconds Timeout (seconds)
     */
    public function __construct(int $seconds = 30)
    {
        $this->seconds = $seconds;
    }

    public function process(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        // 在 header 中標記超時設定（供 HTTP 客戶端使用）
        $request = $request->withHeader('X-Timeout', (string) $this->seconds);

        try {
            return $next($request);
        } catch (ClientExceptionInterface $e) {
            // 檢查是否為超時錯誤
            if ($this->isTimeoutException($e)) {
                return $this->createTimeoutResponse();
            }

            throw $e;
        }
    }

    private function isTimeoutException(ClientExceptionInterface $e): bool
    {
        $message = strtolower($e->getMessage());

        return Str::contains($message, 'timeout')
            || Str::contains($message, 'timed out');
    }

    private function createTimeoutResponse(): ResponseInterface
    {
        $body = json_encode([
            'error' => 'Gateway Timeout',
            'status' => 504,
            'timeout' => $this->seconds,
        ]);

        return new Response(
            504,
            ['Content-Type' => 'application/json'],
            $body
        );
    }
}
