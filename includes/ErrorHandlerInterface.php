<?php

namespace ReverseProxy;

use Psr\Http\Message\RequestInterface;
use Throwable;

interface ErrorHandlerInterface
{
    public function handle(Throwable $exception, RequestInterface $request): void;
}
