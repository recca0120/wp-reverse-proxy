<?php

namespace Recca0120\ReverseProxy\Middleware;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Recca0120\ReverseProxy\Contracts\MiddlewareInterface;
use Recca0120\ReverseProxy\Support\Arr;

/**
 * Rewrite response body content.
 */
class RewriteBody implements MiddlewareInterface
{
    /** @var array<string, string> */
    private $replacements;

    /** @var StreamFactoryInterface */
    private $streamFactory;

    /** @var array<string> */
    private static $textContentTypes = [
        'text/html',
        'text/css',
        'text/javascript',
        'text/xml',
        'text/plain',
        'application/json',
        'application/javascript',
        'application/x-javascript',
        'application/xml',
        'application/xhtml+xml',
        'application/rss+xml',
        'application/atom+xml',
    ];

    /**
     * @param array<string,string> $replacements Replacements (labels: Pattern \(regex\)|Replacement)
     * @param StreamFactoryInterface|null $streamFactory
     */
    public function __construct(
        array $replacements = [],
        ?StreamFactoryInterface $streamFactory = null
    ) {
        $this->replacements = $replacements;
        $this->streamFactory = $streamFactory ?? new Psr17Factory();
    }

    public function process(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        $response = $next($request);

        if (! $this->shouldRewrite($response)) {
            return $response;
        }

        $body = (string) $response->getBody();
        $rewrittenBody = $this->rewrite($body);

        return $response->withBody($this->streamFactory->createStream($rewrittenBody));
    }

    private function shouldRewrite(ResponseInterface $response): bool
    {
        if (empty($this->replacements)) {
            return false;
        }

        $contentType = $response->getHeaderLine('Content-Type');
        if ($contentType === '') {
            return false;
        }

        // Extract media type (ignore charset and other parameters)
        $mediaType = strtolower(trim(explode(';', $contentType)[0]));

        return Arr::contains(self::$textContentTypes, $mediaType);
    }

    private function rewrite(string $body): string
    {
        return preg_replace(
            array_keys($this->replacements),
            array_values($this->replacements),
            $body
        );
    }
}
