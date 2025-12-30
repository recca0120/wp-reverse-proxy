<?php

namespace ReverseProxy\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReverseProxy\Contracts\MiddlewareInterface;

class SetHost implements MiddlewareInterface
{
    /** @var string */
    private $host;

    public function __construct(string $host)
    {
        $this->host = $host;
    }

    public function process(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        return $next($request->withHeader('Host', $this->host));
    }
}
