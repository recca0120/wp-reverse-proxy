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

    /**
     * Parse response header lines into associative array.
     *
     * @param  string[]  $headerLines
     */
    private function parseResponseHeaders(array $headerLines, int &$statusCode = null): array
    {
        $headers = [];

        foreach ($headerLines as $line) {
            if (preg_match('/^HTTP\/[\d.]+ (\d+)/', $line, $matches)) {
                $statusCode = (int) $matches[1];

                continue;
            }

            $parsed = $this->parseHeaderLine($line);
            if ($parsed !== null) {
                $this->addHeader($headers, $parsed[0], $parsed[1]);
            }
        }

        return $headers;
    }
}
