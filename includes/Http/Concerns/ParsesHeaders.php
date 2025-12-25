<?php

namespace ReverseProxy\Http\Concerns;

use Psr\Http\Message\RequestInterface;

trait ParsesHeaders
{
    /**
     * Format request headers as ["Name: value", ...] array.
     */
    private function formatHeaderLines(RequestInterface $request): array
    {
        $headers = [];

        foreach ($request->getHeaders() as $name => $values) {
            $headers[] = $name.': '.implode(', ', $values);
        }

        return $headers;
    }

    /**
     * Parse a single header line.
     *
     * @return array{0: string, 1: string}|null Returns [name, value] or null if invalid
     */
    private function parseHeaderLine(string $line): ?array
    {
        if (strpos($line, ':') === false) {
            return null;
        }

        [$name, $value] = explode(':', $line, 2);

        return [trim($name), trim($value)];
    }

    /**
     * Add header to array, handling duplicate headers.
     */
    private function addHeader(array &$headers, string $name, string $value): void
    {
        if (isset($headers[$name])) {
            $headers[$name][] = $value;
        } else {
            $headers[$name] = [$value];
        }
    }
}
