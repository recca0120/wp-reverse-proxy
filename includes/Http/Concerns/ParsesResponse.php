<?php

namespace Recca0120\ReverseProxy\Http\Concerns;

use Psr\Http\Message\RequestInterface;
use Recca0120\ReverseProxy\Support\Arr;
use Recca0120\ReverseProxy\Support\Str;

trait ParsesResponse
{
    private function prepareHeaders(RequestInterface $request): array
    {
        $headers = [];

        foreach ($request->getHeaders() as $name => $values) {
            $headers[] = $name.': '.implode(', ', $values);
        }

        return $headers;
    }

    private function findHeaderName(array $headers, string $name): ?string
    {
        $lowerName = strtolower($name);

        foreach ($headers as $key => $value) {
            if (strtolower($key) === $lowerName) {
                return $key;
            }
        }

        return null;
    }

    /**
     * @return array{0: string, 1: int, 2: string, 3: array}
     */
    private function parseResponseHeaders(array $headerLines): array
    {
        $protocolVersion = '1.1';
        $statusCode = 200;
        $reasonPhrase = '';
        $headers = [];

        foreach ($headerLines as $line) {
            if (preg_match('/^HTTP\/([\d.]+) (\d+)\s*(.*)/', $line, $matches)) {
                $protocolVersion = $matches[1];
                $statusCode = (int) $matches[2];
                $reasonPhrase = trim($matches[3]);

                continue;
            }

            if (! Str::contains($line, ':')) {
                continue;
            }

            [$name, $value] = explode(':', $line, 2);
            $name = trim($name);
            $value = trim($value);

            if (Arr::has($headers, $name)) {
                $headers[$name][] = $value;
            } else {
                $headers[$name] = [$value];
            }
        }

        return [$protocolVersion, $statusCode, $reasonPhrase, $headers];
    }
}
