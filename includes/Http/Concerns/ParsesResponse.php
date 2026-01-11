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
     * Decode response body and remove Content-Encoding header.
     *
     * @return array{0: string, 1: array}
     */
    private function decodeBody(string $body, array $headers, bool $decodeContent, bool $manualDecode = false): array
    {
        if (! $decodeContent) {
            return [$body, $headers];
        }

        $encodingName = $this->findHeaderName($headers, 'Content-Encoding');

        if ($encodingName === null) {
            return [$body, $headers];
        }

        if ($manualDecode) {
            $encoding = strtolower($headers[$encodingName][0] ?? '');
            $decoded = $this->decompressBody($body, $encoding);

            if ($decoded === null) {
                return [$body, $headers];
            }

            $body = $decoded;
        }

        unset($headers[$encodingName]);

        return [$body, $headers];
    }

    private function decompressBody(string $body, string $encoding): ?string
    {
        switch ($encoding) {
            case 'gzip':
                $decoded = @gzdecode($body);

                return $decoded !== false ? $decoded : null;
            case 'deflate':
                $decoded = @gzinflate($body);

                return $decoded !== false ? $decoded : null;
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
