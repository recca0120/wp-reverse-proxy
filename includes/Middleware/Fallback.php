<?php

namespace Recca0120\ReverseProxy\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Recca0120\ReverseProxy\Contracts\MiddlewareInterface;
use Recca0120\ReverseProxy\Exceptions\FallbackException;

class Fallback implements MiddlewareInterface
{
    /** @var int */
    public $priority = 100;

    /** @var int[] */
    private $statusCodes;

    /**
     * @param  int  ...$statusCodes  觸發 fallback 的狀態碼
     */
    public function __construct(int ...$statusCodes)
    {
        $this->statusCodes = $statusCodes ?: [404];
    }

    public function process(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        $response = $next($request);

        if (in_array($response->getStatusCode(), $this->statusCodes, true)) {
            throw new FallbackException();
        }

        return $response;
    }
}
