<?php

namespace ReverseProxy\Middleware;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use ReverseProxy\MiddlewareInterface;

class RewritePathMiddleware implements MiddlewareInterface
{
    /** @var string */
    private $pattern;

    /** @var string */
    private $replacement;

    public function __construct(string $pattern, string $replacement)
    {
        $this->pattern = $pattern;
        $this->replacement = $replacement;
    }

    public function process(RequestInterface $request, callable $next): ResponseInterface
    {
        $uri = $request->getUri();
        $path = $uri->getPath();

        $newPath = $this->rewritePath($path);

        if ($newPath !== $path) {
            $request = $request->withUri($uri->withPath($newPath));
        }

        return $next($request);
    }

    private function rewritePath(string $path): string
    {
        $regex = $this->patternToRegex($this->pattern);

        if (! preg_match($regex, $path, $matches)) {
            return $path;
        }

        $result = $this->replacement;

        for ($i = 1; $i < count($matches); $i++) {
            $result = str_replace('$'.$i, $matches[$i], $result);
        }

        return $result;
    }

    private function patternToRegex(string $pattern): string
    {
        return '#^'.str_replace('\*', '(.*?)', preg_quote($pattern, '#')).'$#';
    }
}
