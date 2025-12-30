<?php

namespace ReverseProxy\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use ReverseProxy\Contracts\MiddlewareInterface;
use ReverseProxy\Exceptions\FallbackException;

class Fallback implements MiddlewareInterface
{
    /** @var int */
    public $priority = 100;

    /** @var int[] */
    private $statusCodes;

    /**
     * @param  int[]  $statusCodes  觸發 fallback 的狀態碼
     */
    public function __construct(array $statusCodes = [404])
    {
        $this->statusCodes = $statusCodes;
    }

    public function process(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        $response = $next($request);

        if (in_array($response->getStatusCode(), $this->statusCodes, true)) {
            throw new FallbackException;
        }

        return $response;
    }
}
