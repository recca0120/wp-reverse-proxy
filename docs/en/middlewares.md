# Middleware Reference

[← Back to main documentation](../../README.en.md)

This document provides detailed documentation for all built-in middlewares.

## Table of Contents

- [ProxyHeaders](#proxyheaders)
- [SetHost](#sethost)
- [RewritePath](#rewritepath)
- [RewriteBody](#rewritebody)
- [AllowMethods](#allowmethods)
- [Cors](#cors)
- [RequestId](#requestid)
- [IpFilter](#ipfilter)
- [RateLimiting](#ratelimiting)
- [Caching](#caching)
- [Retry](#retry)
- [CircuitBreaker](#circuitbreaker)
- [Timeout](#timeout)
- [Fallback](#fallback)
- [ErrorHandling](#errorhandling)
- [Logging](#logging)

---

## ProxyHeaders

Adds standard proxy headers to forwarded requests:

```php
use Recca0120\ReverseProxy\Middleware\ProxyHeaders;

new Route('/api/*', 'https://backend.example.com', [
    new ProxyHeaders(),
]);
```

Headers added:
- `X-Real-IP` - Client's IP address
- `X-Forwarded-For` - Chain of proxy IPs
- `X-Forwarded-Host` - Original host name
- `X-Forwarded-Proto` - Original protocol (http/https)
- `X-Forwarded-Port` - Original port
- `Forwarded` - RFC 7239 standard header

**Options Configuration:**

```php
// Override specific values
new ProxyHeaders([
    'clientIp' => '10.0.0.1',      // Override client IP
    'host' => 'example.com',       // Override host
    'scheme' => 'https',           // Override scheme
    'port' => '443',               // Override port
]);

// Only send specific headers (whitelist)
new ProxyHeaders([
    'headers' => ['X-Real-IP', 'X-Forwarded-For'],
]);

// Exclude specific headers (blacklist)
new ProxyHeaders([
    'except' => ['Forwarded', 'X-Forwarded-Port'],
]);
```

---

## SetHost

Sets a custom Host header:

```php
use Recca0120\ReverseProxy\Middleware\SetHost;

new Route('/api/*', 'https://127.0.0.1:8080', [
    new SetHost('api.example.com'),
]);
```

---

## RewritePath

Rewrites the request path using Route's wildcard captures:

```php
use Recca0120\ReverseProxy\Middleware\RewritePath;

// /api/v1/users → /v1/users
new Route('/api/v1/*', 'https://backend.example.com', [
    new RewritePath('/v1/$1'),
]);

// /legacy/users → /api/v2/users
new Route('/legacy/*', 'https://backend.example.com', [
    new RewritePath('/api/v2/$1'),
]);

// Multiple wildcards: /api/users/posts/123 → /v2/users/items/123
new Route('/api/*/posts/*', 'https://backend.example.com', [
    new RewritePath('/v2/$1/items/$2'),
]);
```

`$1`, `$2`, etc. correspond to the captured values from `*` wildcards in the Route path.

---

## RewriteBody

Rewrites response body using regular expressions:

```php
use Recca0120\ReverseProxy\Middleware\RewriteBody;

// Replace backend URL with frontend URL
new Route('/api/*', 'https://backend.example.com', [
    new RewriteBody([
        '#https://backend\.example\.com#' => 'https://frontend.example.com',
    ]),
]);

// Multiple replacement rules
new Route('/api/*', 'https://backend.example.com', [
    new RewriteBody([
        '#https://api\.internal\.com#' => 'https://api.example.com',
        '#/internal/assets/#' => '/assets/',
        '#"debug":\s*true#' => '"debug": false',
    ]),
]);
```

Features:
- Uses PHP `preg_replace()` for replacements
- Only processes text-type responses (HTML, CSS, JavaScript, JSON, XML, etc.)
- Binary files (images, PDFs, etc.) are not processed
- Keys are regex patterns, values are replacement strings

---

## AllowMethods

Restricts allowed HTTP methods and returns 405 Method Not Allowed for others:

```php
use Recca0120\ReverseProxy\Middleware\AllowMethods;

new Route('/api/*', 'https://backend.example.com', [
    new AllowMethods('GET', 'POST'),
]);
```

**Config file format:**
```json
"middlewares": ["AllowMethods:GET,POST,PUT"]
```

Features:
- Returns 405 with `Allow` header listing permitted methods
- Always allows OPTIONS for CORS preflight
- Case-insensitive method matching

**Route method vs AllowMethods:**

| Aspect | Route Method (`POST /api/*`) | AllowMethods |
|--------|------------------------------|------------------------|
| No match behavior | Skips to next route | Returns 405 response |
| Use case | Route to different backends | Restrict methods on a route |

---

## Cors

Handles Cross-Origin Resource Sharing (CORS):

```php
use Recca0120\ReverseProxy\Middleware\Cors;

// Basic usage: allow specific origins
new Route('/api/*', 'https://backend.example.com', [
    new Cors(['https://example.com', 'https://app.example.com']),
]);

// Allow all origins
new Route('/api/*', 'https://backend.example.com', [
    new Cors(['*']),
]);

// Full configuration
new Route('/api/*', 'https://backend.example.com', [
    new Cors(
        ['https://example.com'],           // Allowed origins
        ['GET', 'POST', 'PUT', 'DELETE'],  // Allowed methods
        ['Content-Type', 'Authorization'], // Allowed headers
        true,                              // Allow credentials
        86400                              // Preflight cache time (seconds)
    ),
]);
```

Features:
- Automatically handles OPTIONS preflight requests (returns 204)
- Adds `Access-Control-Allow-Origin` and related headers
- Supports multiple origins or wildcard `*`
- Configurable credentials support (cookies)

---

## RequestId

Generates or propagates request tracking ID:

```php
use Recca0120\ReverseProxy\Middleware\RequestId;

// Use default header name X-Request-ID
new Route('/api/*', 'https://backend.example.com', [
    new RequestId(),
]);

// Use custom header name
new Route('/api/*', 'https://backend.example.com', [
    new RequestId('X-Correlation-ID'),
]);
```

Features:
- Preserves existing ID if present in request
- Generates UUID v4 format ID if none exists
- Adds ID to response header for tracing

---

## IpFilter

IP whitelist/blacklist filtering:

```php
use Recca0120\ReverseProxy\Middleware\IpFilter;

// Whitelist mode: only allow specified IPs
new Route('/api/*', 'https://backend.example.com', [
    IpFilter::allow('192.168.1.100', '10.0.0.1'),
]);

// Blacklist mode: block specified IPs
new Route('/api/*', 'https://backend.example.com', [
    IpFilter::deny('192.168.1.100'),
]);

// Support CIDR notation
new Route('/api/*', 'https://backend.example.com', [
    IpFilter::allow('192.168.1.0/24', '10.0.0.0/8'),
]);
```

**Config file format:**
```json
"middlewares": [
  "IpFilter:allow,192.168.1.100,10.0.0.1",
  "IpFilter:deny,192.168.1.100",
  "IpFilter:192.168.1.0/24"
]
```

> If mode (allow/deny) is omitted, defaults to `allow`.

Features:
- Supports whitelist (allow) and blacklist (deny) modes
- Supports CIDR notation (e.g., `192.168.1.0/24`)
- Blocked requests return 403 Forbidden

---

## RateLimiting

Request rate limiting:

```php
use Recca0120\ReverseProxy\Middleware\RateLimiting;

// 60 requests per minute
new Route('/api/*', 'https://backend.example.com', [
    new RateLimiting(60, 60),
]);

// 1000 requests per hour
new Route('/api/*', 'https://backend.example.com', [
    new RateLimiting(1000, 3600),
]);

// Custom rate limit key (e.g., by API key)
new Route('/api/*', 'https://backend.example.com', [
    new RateLimiting(100, 60, function ($request) {
        return $request->getHeaderLine('X-API-Key');
    }),
]);
```

Features:
- Rate limits by IP address by default
- Includes `X-RateLimit-*` headers in response
- Returns 429 Too Many Requests with `Retry-After` header when exceeded

---

## Caching

Response caching:

```php
use Recca0120\ReverseProxy\Middleware\Caching;

// Cache for 5 minutes
new Route('/api/*', 'https://backend.example.com', [
    new Caching(300),
]);

// Cache for 1 hour
new Route('/api/*', 'https://backend.example.com', [
    new Caching(3600),
]);
```

Features:
- Only caches GET/HEAD requests
- Only caches 200 OK responses
- Respects `Cache-Control: no-cache/no-store/private`
- Includes `X-Cache: HIT/MISS` header in response
- Uses WordPress transients for storage

---

## Retry

Automatic retry on failure:

```php
use Recca0120\ReverseProxy\Middleware\Retry;

// Retry up to 3 times
new Route('/api/*', 'https://backend.example.com', [
    new Retry(3),
]);

// Custom retryable methods and status codes
new Route('/api/*', 'https://backend.example.com', [
    new Retry(
        3,                            // Max retries
        ['GET', 'PUT', 'DELETE'],     // Retryable methods
        [502, 503, 504]               // Retryable status codes
    ),
]);
```

Features:
- Only retries GET/HEAD/OPTIONS requests by default
- Retries on 502/503/504 or network errors
- Does not retry 4xx errors (client errors)

---

## CircuitBreaker

Circuit breaker pattern:

```php
use Recca0120\ReverseProxy\Middleware\CircuitBreaker;

new Route('/api/*', 'https://backend.example.com', [
    new CircuitBreaker(
        'my-service',  // Service name (identifies different circuits)
        5,             // Failure threshold
        60             // Reset timeout (seconds)
    ),
]);
```

Features:
- Opens circuit after consecutive failures reach threshold
- Returns 503 immediately when circuit is open
- Auto-recovers after timeout
- Successful requests reset failure count

---

## Timeout

Request timeout control:

```php
use Recca0120\ReverseProxy\Middleware\Timeout;

// 30 second timeout
new Route('/api/*', 'https://backend.example.com', [
    new Timeout(30),
]);

// 5 second timeout (fail fast)
new Route('/api/*', 'https://backend.example.com', [
    new Timeout(5),
]);
```

Features:
- Sets request timeout duration
- Returns 504 Gateway Timeout on timeout
- Passes timeout via `X-Timeout` header

---

## Fallback

Fall back to WordPress when backend returns specified status codes:

```php
use Recca0120\ReverseProxy\Middleware\Fallback;

// Fallback on 404 (default)
new Route('/api/*', 'https://backend.example.com', [
    new Fallback(),
]);

// Multiple status codes
new Route('/api/*', 'https://backend.example.com', [
    new Fallback(404, 410, 500),
]);
```

**Config file format:**
```json
"middlewares": ["Fallback:404,500,502"]
```

Features:
- Defaults to fallback on 404 only
- WordPress continues to handle the request (e.g., shows WordPress 404 page)
- Useful for letting WordPress handle missing resources

---

## ErrorHandling

Catches HTTP client exceptions and returns 502 Bad Gateway:

```php
use Recca0120\ReverseProxy\Middleware\ErrorHandling;

new Route('/api/*', 'https://backend.example.com', [
    new ErrorHandling(),
]);
```

Features:
- Catches `ClientExceptionInterface` (connection timeout, refused, etc.)
- Returns 502 status code with JSON error message
- Other exceptions are re-thrown

> **Note**: This middleware is automatically added to all routes by default.

---

## Logging

Logs proxy requests and responses:

```php
use Recca0120\ReverseProxy\Middleware\Logging;
use Recca0120\ReverseProxy\WordPress\Logger;

new Route('/api/*', 'https://backend.example.com', [
    new Logging(new Logger()),
]);
```

Logged information:
- Request: HTTP method, target URL
- Response: status code
- Error: exception message (then re-throws)

> **Note**: This middleware is automatically added to all routes by default.

---

## Custom Middleware

### Using Closures

```php
$route = (new Route('/api/*', 'https://backend.example.com'))
    ->middleware(function ($request, $next) {
        // Before: modify request
        $request = $request->withHeader('Authorization', 'Bearer token');

        // Execute next middleware or proxy
        $response = $next($request);

        // After: modify response
        return $response->withHeader('X-Processed', 'true');
    });
```

### Using MiddlewareInterface

For reusable middleware classes:

> **Namespace Note**
>
> PSR interface namespaces differ between production and development versions:
>
> | Version | Namespace |
> |---------|-----------|
> | Production | `Recca0120\ReverseProxy\Vendor\Psr\Http\Message\*` |
> | Development | `Psr\Http\Message\*` |
>
> Examples below use **production** namespaces. For development, replace `Recca0120\ReverseProxy\Vendor\Psr\*` with `Psr\*`.

```php
use Recca0120\ReverseProxy\Contracts\MiddlewareInterface;
use Recca0120\ReverseProxy\Vendor\Psr\Http\Message\ServerRequestInterface;
use Recca0120\ReverseProxy\Vendor\Psr\Http\Message\ResponseInterface;

class AddAuthHeader implements MiddlewareInterface
{
    private $token;

    public function __construct(string $token)
    {
        $this->token = $token;
    }

    public function process(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        return $next($request->withHeader('Authorization', 'Bearer ' . $this->token));
    }
}

// Usage
$route = (new Route('/api/*', 'https://backend.example.com'))
    ->middleware(new AddAuthHeader('my-secret-token'));
```

### Middleware Examples

**Cache Responses:**
```php
->middleware(function ($request, $next) {
    $cacheKey = md5((string) $request->getUri());

    if ($cached = wp_cache_get($cacheKey, 'proxy')) {
        return $cached;  // Short-circuit: skip proxy
    }

    $response = $next($request);
    wp_cache_set($cacheKey, $response, 'proxy', 300);

    return $response;
})
```

**Measure Request Time:**
```php
->middleware(function ($request, $next) {
    $start = microtime(true);
    $response = $next($request);
    $elapsed = microtime(true) - $start;

    return $response->withHeader('X-Response-Time', round($elapsed * 1000) . 'ms');
})
```

### Chaining Middlewares

Using fluent API:
```php
$route = (new Route('/api/*', 'https://backend.example.com'))
    ->middleware(new ProxyHeaders())
    ->middleware(new SetHost('api.example.com'))
    ->middleware($authMiddleware)
    ->middleware($loggingMiddleware);
```

Or via constructor:
```php
$route = new Route('/api/*', 'https://backend.example.com', [
    new ProxyHeaders(),
    new SetHost('api.example.com'),
    $authMiddleware,
    $loggingMiddleware,
]);
```

Middlewares execute in onion-style order:
```
Request → [MW1 → [MW2 → [MW3 → Proxy] ← MW3] ← MW2] ← MW1 → Response
```

### Middleware Priority

Middlewares can define a `$priority` property to control execution order (lower numbers execute first, i.e., outermost layer):

```php
use Recca0120\ReverseProxy\Contracts\MiddlewareInterface;
use Recca0120\ReverseProxy\Vendor\Psr\Http\Message\ServerRequestInterface;
use Recca0120\ReverseProxy\Vendor\Psr\Http\Message\ResponseInterface;

class AuthMiddleware implements MiddlewareInterface
{
    public $priority = -50;  // Executes before default (0)

    public function process(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        return $next($request->withHeader('Authorization', 'Bearer token'));
    }
}
```

Built-in priorities:
- `ErrorHandling`: -100 (outermost, catches all errors)
- `Logging`: -90 (logs all requests)
- Custom middlewares default: 0
