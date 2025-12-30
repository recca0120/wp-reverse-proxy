<?php

namespace ReverseProxy\Contracts;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface MiddlewareInterface
{
    public function process(ServerRequestInterface $request, callable $next): ResponseInterface;
}
