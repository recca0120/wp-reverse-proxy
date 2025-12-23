<?php

namespace ReverseProxy;

use Psr\Http\Message\ServerRequestInterface;

class Rule
{
    /** @var string */
    private $source;

    /** @var string */
    private $target;

    /** @var string|null */
    private $rewrite;

    /** @var bool */
    private $preserveHost;

    public function __construct(string $source, string $target, ?string $rewrite = null, bool $preserveHost = false)
    {
        $this->source = $source;
        $this->target = $target;
        $this->rewrite = $rewrite;
        $this->preserveHost = $preserveHost;
    }

    public function matches(ServerRequestInterface $request): ?string
    {
        $uri = $request->getUri();
        $path = $uri->getPath() ?: '/';
        $queryString = $uri->getQuery() ?: '';

        $captures = [];
        if (! $this->matchesPattern($path, $captures)) {
            return null;
        }

        return $this->buildTargetUrl($path, $queryString, $captures);
    }

    public function shouldPreserveHost(): bool
    {
        return $this->preserveHost;
    }

    public function getTargetHost(): string
    {
        return parse_url($this->target, PHP_URL_HOST) ?: '';
    }

    private function matchesPattern(string $path, ?array &$captures = null): bool
    {
        $regex = '#^' . str_replace('\*', '(.*)', preg_quote($this->source, '#')) . '$#';

        if (preg_match($regex, $path, $matched)) {
            $captures = array_slice($matched, 1);

            return true;
        }

        return false;
    }

    private function buildTargetUrl(string $path, string $queryString, array $captures = []): string
    {
        if ($this->rewrite !== null) {
            $rewrittenPath = $this->rewrite;

            foreach ($captures as $index => $capture) {
                $rewrittenPath = str_replace('$' . ($index + 1), $capture, $rewrittenPath);
            }

            $url = rtrim($this->target, '/') . $rewrittenPath;
        } else {
            $url = rtrim($this->target, '/') . $path;
        }

        if ($queryString !== '') {
            $url .= '?' . $queryString;
        }

        return $url;
    }
}
