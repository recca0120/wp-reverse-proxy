<?php

namespace Recca0120\ReverseProxy\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Recca0120\ReverseProxy\Contracts\MiddlewareInterface;
use Recca0120\ReverseProxy\Exceptions\FallbackException;
use Recca0120\ReverseProxy\Support\Arr;

/**
 * Provide fallback response on failure.
 */
class Fallback implements MiddlewareInterface
{
    /** @var int */
    public $priority = 100;

    /** @var int[] */
    private $statusCodes;

    /**
     * @param int|int[] $statusCodes Trigger Status Codes (default: 404)
     */
    public function __construct(...$statusCodes)
    {
        $this->statusCodes = array_map('intval', Arr::wrap($statusCodes)) ?: [404];
    }

    public function process(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        $response = $next($request);

        if (Arr::contains($this->statusCodes, $response->getStatusCode())) {
            throw new FallbackException();
        }

        return $response;
    }
}
