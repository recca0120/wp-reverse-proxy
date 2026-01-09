<?php

namespace Recca0120\ReverseProxy\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Recca0120\ReverseProxy\Contracts\MiddlewareInterface;

/**
 * Override the Host header.
 */
class SetHost implements MiddlewareInterface
{
    /** @var string */
    private $host;

    /**
     * @param string $host Host
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
