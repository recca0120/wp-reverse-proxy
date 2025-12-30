<?php

namespace ReverseProxy\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use ReverseProxy\Contracts\MiddlewareInterface;

class RequestId implements MiddlewareInterface
{
    /** @var string */
    private $headerName;

    public function __construct(string $headerName = 'X-Request-ID')
    {
        $this->headerName = $headerName;
    }

    public function process(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        // 使用現有的 Request ID 或產生新的
        $requestId = $request->getHeaderLine($this->headerName);
        if ($requestId === '') {
            $requestId = $this->generateId();
        }

        // 加入到請求中
        $request = $request->withHeader($this->headerName, $requestId);

        // 執行下一個 middleware
        $response = $next($request);

        // 加入到回應中
        return $response->withHeader($this->headerName, $requestId);
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
