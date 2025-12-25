<?php

namespace ReverseProxy\Http\Concerns;

use Psr\Http\Message\RequestInterface;

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

    private function parseStatusCode(array $headerLines): int
    {
        foreach ($headerLines as $line) {
            if (preg_match('/^HTTP\/[\d.]+ (\d+)/', $line, $matches)) {
                return (int) $matches[1];
            }
        }

        return 200;
    }

    private function parseResponseHeaders(array $headerLines): array
    {
        $headers = [];

        foreach ($headerLines as $line) {
            if (strpos($line, ':') === false) {
                continue;
            }

            [$name, $value] = explode(':', $line, 2);
            $name = trim($name);
            $value = trim($value);

            if (isset($headers[$name])) {
                $headers[$name][] = $value;
            } else {
                $headers[$name] = [$value];
            }
        }

        return $headers;
    }
}
