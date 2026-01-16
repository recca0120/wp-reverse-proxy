<?php

namespace Recca0120\ReverseProxy\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Recca0120\ReverseProxy\Contracts\MiddlewareInterface;

/**
 * Add a unique request ID header.
 */
class RequestId implements MiddlewareInterface
{
    /** @var string */
    private $header;

    /**
     * @param string $header Header name for request ID
     */
    public function __construct(string $header = 'X-Request-ID')
    {
        $this->header = $header;
    }

    public function process(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        // 使用現有的 Request ID 或產生新的
        $requestId = $request->getHeaderLine($this->header);
        if ($requestId === '') {
            $requestId = $this->generateId();
        }

        // 加入到請求中
        $request = $request->withHeader($this->header, $requestId);

        // 執行下一個 middleware
        $response = $next($request);

        // 加入到回應中
        return $response->withHeader($this->header, $requestId);
    }

    private function generateId(): string
    {
        // UUID v4 格式
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0F | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3F | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
