<?php

namespace ReverseProxy\Middleware;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use ReverseProxy\MiddlewareInterface;

class SetHostMiddleware implements MiddlewareInterface
{
    /** @var string */
    private $host;

    public function __construct(string $host)
    {
        $this->host = $host;
    }

    public function process(RequestInterface $request, callable $next): ResponseInterface
    {
        return $next($request->withHeader('Host', $this->host));
    }
}
