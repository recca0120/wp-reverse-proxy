<?php

namespace Recca0120\ReverseProxy\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Recca0120\ReverseProxy\Contracts\MiddlewareInterface;

/**
 * @UIDescription("Override the Host header")
 */
class SetHost implements MiddlewareInterface
{
    /** @var string */
    private $host;

    /**
     * @UIField(name="host", type="text", label="Host", required=true)
     */
    public function __construct(string $host)
    {
        $this->host = $host;
    }

    public function process(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        return $next($request->withHeader('Host', $this->host));
    }
}
