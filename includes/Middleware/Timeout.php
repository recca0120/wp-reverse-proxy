<?php

namespace ReverseProxy\Middleware;

use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReverseProxy\Contracts\MiddlewareInterface;

class Timeout implements MiddlewareInterface
{
    /** @var int */
    public $priority = -60;

    /** @var int */
    private $seconds;

    /**
     * @param  int  $seconds  超時時間（秒）
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
        } catch (ClientExceptionInterface $clientException) {
            // 檢查是否為超時錯誤
            if ($this->isTimeoutException($clientException)) {
                return $this->createTimeoutResponse();
            }

            throw $clientException;
        }
    }

    private function isTimeoutException(ClientExceptionInterface $e): bool
    {
        $message = strtolower($e->getMessage());

        return strpos($message, 'timeout') !== false
            || strpos($message, 'timed out') !== false;
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
