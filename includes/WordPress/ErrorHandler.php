<?php

namespace ReverseProxy\WordPress;

use Psr\Http\Message\RequestInterface;
use ReverseProxy\ErrorHandlerInterface;
use Throwable;

class ErrorHandler implements ErrorHandlerInterface
{
    public function handle(Throwable $exception, RequestInterface $request): void
    {
        do_action('reverse_proxy_error', $exception, $request);
    }
}
