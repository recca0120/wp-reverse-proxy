<?php

namespace ReverseProxy\Middleware;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use ReverseProxy\MiddlewareInterface;

class LoggingMiddleware implements MiddlewareInterface
{
    /** @var int */
    public $priority = -90;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function process(RequestInterface $request, callable $next): ResponseInterface
    {
        $this->logger->info('Proxying request', [
            'method' => $request->getMethod(),
            'target' => (string) $request->getUri(),
        ]);

        try {
            $response = $next($request);

            $this->logger->info('Proxy response received', [
                'status' => $response->getStatusCode(),
                'target' => (string) $request->getUri(),
            ]);

            return $response;
        } catch (ClientExceptionInterface $e) {
            $this->logger->error('Proxy error: ' . $e->getMessage(), [
                'target' => (string) $request->getUri(),
                'exception' => get_class($e),
            ]);

            throw $e;
        }
    }
}
