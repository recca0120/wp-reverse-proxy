<?php

namespace Recca0120\ReverseProxy\WordPress\Admin;

class MiddlewareRegistry
{
    public static function getAll(): array
    {
        return [
            'ProxyHeaders' => [
                'label' => 'Proxy Headers',
                'description' => 'Add X-Forwarded-* headers to the proxied request',
                'fields' => [],
            ],
            'SetHost' => [
                'label' => 'Set Host',
                'description' => 'Override the Host header',
                'fields' => [
                    ['name' => 'host', 'type' => 'text', 'label' => 'Host', 'required' => true],
                ],
            ],
            'Timeout' => [
                'label' => 'Timeout',
                'description' => 'Set request timeout',
                'fields' => [
                    ['name' => 'timeout', 'type' => 'number', 'label' => 'Seconds', 'default' => 30],
                ],
            ],
            'AllowMethods' => [
                'label' => 'Allow Methods',
                'description' => 'Restrict allowed HTTP methods',
                'fields' => [
                    ['name' => 'methods', 'type' => 'text', 'label' => 'Methods (comma-separated)', 'placeholder' => 'GET, POST, PUT'],
                ],
            ],
            'RewritePath' => [
                'label' => 'Rewrite Path',
                'description' => 'Rewrite the request path using pattern',
                'fields' => [
                    ['name' => 'replacement', 'type' => 'text', 'label' => 'Replacement Pattern', 'placeholder' => '/api/v1/$1'],
                ],
            ],
            'Cors' => [
                'label' => 'CORS',
                'description' => 'Add CORS headers to the response',
                'fields' => [
                    ['name' => 'allowOrigin', 'type' => 'text', 'label' => 'Allow Origin', 'default' => '*'],
                    ['name' => 'allowMethods', 'type' => 'text', 'label' => 'Allow Methods', 'default' => 'GET, POST, PUT, DELETE, OPTIONS'],
                    ['name' => 'allowHeaders', 'type' => 'text', 'label' => 'Allow Headers', 'default' => 'Content-Type, Authorization'],
                ],
            ],
            'RateLimiting' => [
                'label' => 'Rate Limiting',
                'description' => 'Limit the number of requests',
                'fields' => [
                    ['name' => 'maxRequests', 'type' => 'number', 'label' => 'Max Requests', 'default' => 100],
                    ['name' => 'windowSeconds', 'type' => 'number', 'label' => 'Window (seconds)', 'default' => 60],
                ],
            ],
            'Caching' => [
                'label' => 'Caching',
                'description' => 'Cache responses',
                'fields' => [
                    ['name' => 'ttl', 'type' => 'number', 'label' => 'TTL (seconds)', 'default' => 300],
                ],
            ],
            'Retry' => [
                'label' => 'Retry',
                'description' => 'Retry failed requests',
                'fields' => [
                    ['name' => 'maxRetries', 'type' => 'number', 'label' => 'Max Retries', 'default' => 3],
                    ['name' => 'delay', 'type' => 'number', 'label' => 'Delay (ms)', 'default' => 100],
                ],
            ],
            'CircuitBreaker' => [
                'label' => 'Circuit Breaker',
                'description' => 'Prevent cascading failures',
                'fields' => [
                    ['name' => 'serviceName', 'type' => 'text', 'label' => 'Service Name', 'required' => true],
                    ['name' => 'failureThreshold', 'type' => 'number', 'label' => 'Failure Threshold', 'default' => 5],
                    ['name' => 'resetTimeout', 'type' => 'number', 'label' => 'Reset Timeout (seconds)', 'default' => 30],
                ],
            ],
            'IpFilter' => [
                'label' => 'IP Filter',
                'description' => 'Filter requests by IP address',
                'fields' => [
                    ['name' => 'allowList', 'type' => 'textarea', 'label' => 'Allow List (one per line)'],
                    ['name' => 'denyList', 'type' => 'textarea', 'label' => 'Deny List (one per line)'],
                ],
            ],
            'RequestId' => [
                'label' => 'Request ID',
                'description' => 'Add a unique request ID header',
                'fields' => [
                    ['name' => 'headerName', 'type' => 'text', 'label' => 'Header Name', 'default' => 'X-Request-ID'],
                ],
            ],
            'Logging' => [
                'label' => 'Logging',
                'description' => 'Log requests and responses',
                'fields' => [],
            ],
            'ErrorHandling' => [
                'label' => 'Error Handling',
                'description' => 'Handle errors gracefully',
                'fields' => [],
            ],
            'Fallback' => [
                'label' => 'Fallback',
                'description' => 'Provide fallback response on failure',
                'fields' => [
                    ['name' => 'statusCode', 'type' => 'number', 'label' => 'Status Code', 'default' => 503],
                    ['name' => 'body', 'type' => 'textarea', 'label' => 'Response Body'],
                ],
            ],
            'RewriteBody' => [
                'label' => 'Rewrite Body',
                'description' => 'Rewrite response body content',
                'fields' => [
                    ['name' => 'search', 'type' => 'text', 'label' => 'Search Pattern'],
                    ['name' => 'replace', 'type' => 'text', 'label' => 'Replace With'],
                ],
            ],
            'SanitizeHeaders' => [
                'label' => 'Sanitize Headers',
                'description' => 'Remove sensitive headers from requests/responses',
                'fields' => [],
            ],
        ];
    }
}
