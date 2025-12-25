# WP Reverse Proxy

[繁體中文](README.md) | **English**

A WordPress plugin that proxies specific URL paths to external backend servers.

## Features

- Route matching with wildcard support (`/api/*`)
- HTTP method matching (`POST /api/users`, `GET|POST /api/*`)
- Path rewriting via middleware
- Full HTTP method support (GET, POST, PUT, PATCH, DELETE)
- Request/Response headers forwarding
- Query string preservation
- Customizable Host header
- Standard proxy headers (X-Real-IP, X-Forwarded-For, etc.)
- Error handling with 502 Bad Gateway
- Logging integration
- PSR-18 compliant HTTP client
- Middleware support for request/response processing

## Requirements

- PHP 7.2+
- WordPress 5.0+

## Installation

### Download from GitHub Releases (Recommended)

1. Go to the [Releases page](https://github.com/recca0120/wp-reverse-proxy/releases)
2. Download the latest `reverse-proxy.zip`
3. In WordPress admin → Plugins → Add New → Upload Plugin
4. Upload the zip file and activate

### Via Composer

```bash
composer require recca0120/wp-reverse-proxy
```

### Manual Installation (For Development)

1. Clone the project to `/wp-content/plugins/wp-reverse-proxy/`
2. Run `composer install` in the plugin directory
3. Activate the plugin in WordPress admin

## Usage

### Basic Configuration

Create a configuration file at `wp-content/mu-plugins/reverse-proxy-config.php`:

```php
<?php
/**
 * Plugin Name: Reverse Proxy Config
 * Description: Custom reverse proxy routes configuration
 */

use ReverseProxy\Route;

add_filter('reverse_proxy_routes', function () {
    return [
        new Route('/api/*', 'https://api.example.com'),
    ];
});
```

> **Note**: The `mu-plugins` folder does not exist by default and must be created manually.

> **Why use mu-plugins?**
> - Auto-loaded, no activation required
> - Theme-independent
> - Loads before regular plugins
> - Ideal for infrastructure-level configuration
>
> See [WordPress MU-Plugins Documentation](https://developer.wordpress.org/advanced-administration/plugins/mu-plugins/)

**Alternative locations** (not recommended):
- `functions.php` - Lost when switching themes
- Custom plugin - Can be deactivated

### Multiple Routes

Routes are matched in order (first match wins):

```php
use ReverseProxy\Route;

add_filter('reverse_proxy_routes', function () {
    return [
        // More specific routes first
        new Route('/api/v2/*', 'https://api-v2.example.com'),
        // Fallback for other API routes
        new Route('/api/*', 'https://api.example.com'),
    ];
});
```

### With Middlewares

```php
use ReverseProxy\Route;
use ReverseProxy\Middleware\ProxyHeadersMiddleware;
use ReverseProxy\Middleware\SetHostMiddleware;
use ReverseProxy\Middleware\RewritePathMiddleware;

add_filter('reverse_proxy_routes', function () {
    return [
        new Route('/api/*', 'https://backend.example.com', [
            new ProxyHeadersMiddleware(),
            new SetHostMiddleware('custom-host.com'),
        ]),
    ];
});
```

## Route Constructor

```php
new Route(
    string $source,       // URL pattern to match (supports `*` wildcard and HTTP methods)
    string $target,       // Target server URL
    array $middlewares    // Optional: array of middlewares
);
```

### Source Pattern Formats

**Path only** (matches all HTTP methods):
```php
new Route('/api/*', 'https://backend.example.com');
```

**Single HTTP method**:
```php
new Route('POST /api/users', 'https://backend.example.com');
new Route('DELETE /api/users/*', 'https://backend.example.com');
```

**Multiple HTTP methods** (use `|` separator):
```php
new Route('GET|POST /api/users', 'https://backend.example.com');
new Route('GET|POST|PUT|DELETE /api/*', 'https://backend.example.com');
```

### Method-Based Routing Example

Route different HTTP methods to different backends:

```php
use ReverseProxy\Route;

add_filter('reverse_proxy_routes', function () {
    return [
        // Read operations → read replica
        new Route('GET /api/users/*', 'https://read-replica.example.com'),
        // Write operations → primary server
        new Route('POST|PUT|DELETE /api/users/*', 'https://primary.example.com'),
    ];
});
```

## Built-in Middlewares

### ProxyHeadersMiddleware

Adds standard proxy headers to forwarded requests:

```php
use ReverseProxy\Middleware\ProxyHeadersMiddleware;

new Route('/api/*', 'https://backend.example.com', [
    new ProxyHeadersMiddleware(),
]);
```

Headers added:
- `X-Real-IP` - Client's IP address
- `X-Forwarded-For` - Chain of proxy IPs
- `X-Forwarded-Proto` - Original protocol (http/https)
- `X-Forwarded-Port` - Original port

### SetHostMiddleware

Sets a custom Host header:

```php
use ReverseProxy\Middleware\SetHostMiddleware;

new Route('/api/*', 'https://127.0.0.1:8080', [
    new SetHostMiddleware('api.example.com'),
]);
```

### RewritePathMiddleware

Rewrites the request path:

```php
use ReverseProxy\Middleware\RewritePathMiddleware;

// /api/v1/users → /v1/users
new Route('/api/v1/*', 'https://backend.example.com', [
    new RewritePathMiddleware('/api/v1/*', '/v1/$1'),
]);

// /legacy/users → /api/v2/users
new Route('/legacy/*', 'https://backend.example.com', [
    new RewritePathMiddleware('/legacy/*', '/api/v2/$1'),
]);

// Multiple wildcards: /api/users/posts/123 → /v2/users/items/123
new Route('/api/*/posts/*', 'https://backend.example.com', [
    new RewritePathMiddleware('/api/*/posts/*', '/v2/$1/items/$2'),
]);
```

### AllowMethodsMiddleware

Restricts allowed HTTP methods and returns 405 Method Not Allowed for others:

```php
use ReverseProxy\Middleware\AllowMethodsMiddleware;

new Route('/api/*', 'https://backend.example.com', [
    new AllowMethodsMiddleware(['GET', 'POST']),
]);
```

Features:
- Returns 405 with `Allow` header listing permitted methods
- Always allows OPTIONS for CORS preflight
- Case-insensitive method matching

**Route method vs AllowMethodsMiddleware:**

| Aspect | Route Method (`POST /api/*`) | AllowMethodsMiddleware |
|--------|------------------------------|------------------------|
| No match behavior | Skips to next route | Returns 405 response |
| Use case | Route to different backends | Restrict methods on a route |

### ErrorHandlingMiddleware (Enabled by Default)

Catches HTTP client exceptions and returns 502 Bad Gateway:

```php
use ReverseProxy\Middleware\ErrorHandlingMiddleware;

new Route('/api/*', 'https://backend.example.com', [
    new ErrorHandlingMiddleware(),
]);
```

Features:
- Catches `ClientExceptionInterface` (connection timeout, refused, etc.)
- Returns 502 status code with JSON error message
- Other exceptions are re-thrown

### LoggingMiddleware (Enabled by Default)

Logs proxy requests and responses:

```php
use ReverseProxy\Middleware\LoggingMiddleware;
use ReverseProxy\WordPress\Logger;

new Route('/api/*', 'https://backend.example.com', [
    new LoggingMiddleware(new Logger()),
]);
```

Logged information:
- Request: HTTP method, target URL
- Response: status code
- Error: exception message (then re-throws)

> **Note**: `ErrorHandlingMiddleware` and `LoggingMiddleware` are automatically added to all routes by default. No manual configuration required.

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

```php
use ReverseProxy\MiddlewareInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class AddAuthHeader implements MiddlewareInterface
{
    private $token;

    public function __construct(string $token)
    {
        $this->token = $token;
    }

    public function process(RequestInterface $request, callable $next): ResponseInterface
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
    ->middleware(new ProxyHeadersMiddleware())
    ->middleware(new SetHostMiddleware('api.example.com'))
    ->middleware($authMiddleware)
    ->middleware($loggingMiddleware);
```

Or via constructor:
```php
$route = new Route('/api/*', 'https://backend.example.com', [
    new ProxyHeadersMiddleware(),
    new SetHostMiddleware('api.example.com'),
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
use ReverseProxy\MiddlewareInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class AuthMiddleware implements MiddlewareInterface
{
    public $priority = -50;  // Executes before default (0)

    public function process(RequestInterface $request, callable $next): ResponseInterface
    {
        return $next($request->withHeader('Authorization', 'Bearer token'));
    }
}
```

Built-in priorities:
- `ErrorHandlingMiddleware`: -100 (outermost, catches all errors)
- `LoggingMiddleware`: -90 (logs all requests)
- Custom middlewares default: 0

## Real-World Example

Equivalent to this nginx configuration:

```nginx
location ^~ /api/v1 {
    proxy_pass https://127.0.0.1:8080;
    proxy_set_header Host api.example.com;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header X-Forwarded-Port $server_port;
}
```

WordPress equivalent:

```php
use ReverseProxy\Route;
use ReverseProxy\Middleware\ProxyHeadersMiddleware;
use ReverseProxy\Middleware\SetHostMiddleware;

add_filter('reverse_proxy_routes', function () {
    return [
        new Route('/api/v1/*', 'https://127.0.0.1:8080', [
            new ProxyHeadersMiddleware(),
            new SetHostMiddleware('api.example.com'),
        ]),
    ];
});
```

## Available Hooks

### Filters

| Hook | Parameters | Description |
|------|------------|-------------|
| `reverse_proxy_routes` | `$routes` | Configure proxy routes |
| `reverse_proxy_default_middlewares` | `$middlewares` | Customize default middlewares |
| `reverse_proxy_http_client` | `$client` | Override PSR-18 HTTP client |
| `reverse_proxy_request_body` | `$body` | Override request body (for testing) |
| `reverse_proxy_response` | `$response` | Modify response before sending |
| `reverse_proxy_should_exit` | `$should_exit` | Control exit behavior |

### Actions

| Hook | Parameters | Description |
|------|------------|-------------|
| `reverse_proxy_log` | `$level, $message, $context` | Log proxy events (PSR-3 levels) |

## Hook Examples

### Logging

```php
add_action('reverse_proxy_log', function ($level, $message, $context) {
    error_log(sprintf(
        '[ReverseProxy] [%s] %s %s',
        strtoupper($level),
        $message,
        json_encode($context)
    ));
}, 10, 3);
```

### Error Handling

```php
add_action('reverse_proxy_log', function ($level, $message, $context) {
    if ($level === 'error') {
        wp_mail(
            'admin@example.com',
            'Reverse Proxy Error',
            sprintf('Error: %s', $message)
        );
    }
}, 10, 3);
```

### Modify Response

```php
add_filter('reverse_proxy_response', function ($response) {
    return $response->withHeader('X-Proxied-By', 'WP-Reverse-Proxy');
});
```

### Customize Default Middlewares

```php
// Remove all default middlewares
add_filter('reverse_proxy_default_middlewares', '__return_empty_array');

// Keep only error handling
add_filter('reverse_proxy_default_middlewares', function ($middlewares) {
    return array_filter($middlewares, function ($m) {
        return $m instanceof \ReverseProxy\Middleware\ErrorHandlingMiddleware;
    });
});

// Add custom middleware
add_filter('reverse_proxy_default_middlewares', function ($middlewares) {
    $middlewares[] = new MyCustomMiddleware();
    return $middlewares;
});
```

### Custom HTTP Client

The plugin uses `WordPressHttpClient` (based on `wp_remote_request()`) by default, with two alternative implementations available:

| Client | Dependency | Description |
|--------|------------|-------------|
| `WordPressHttpClient` | WordPress | Default, uses WordPress HTTP API |
| `CurlHttpClient` | curl extension | Direct curl usage, better performance |
| `StreamHttpClient` | None | Pure PHP, uses `file_get_contents` |

```php
// Use CurlHttpClient
add_filter('reverse_proxy_http_client', function () {
    return new \ReverseProxy\Http\CurlHttpClient([
        'timeout' => 30,
        'verify' => true,
    ]);
});

// Use StreamHttpClient (pure PHP, no extensions required)
add_filter('reverse_proxy_http_client', function () {
    return new \ReverseProxy\Http\StreamHttpClient([
        'timeout' => 30,
    ]);
});
```

You can also use any PSR-18 compatible third-party client (e.g., Guzzle). Make sure to disable automatic decompression to preserve original content:

```php
add_filter('reverse_proxy_http_client', function () {
    return new \GuzzleHttp\Client([
        'timeout' => 30,
        'decode_content' => false,
    ]);
});
```

## How It Works

1. Plugin hooks into WordPress `parse_request` action
2. Checks if request path matches any configured route
3. If matched:
   - Forwards request to target server
   - Returns response to client
   - Exits (stops WordPress processing)
4. If not matched:
   - WordPress continues normal request handling

```
Request → parse_request → Match Route?
                              ↓
                    Yes ──→ Proxy → Response → Exit
                              ↓
                    No  ──→ WordPress handles normally
```

## Development

### Setup

```bash
# Install dependencies
composer install

# Setup WordPress test environment
./bin/install-wp-tests.sh

# Run tests
composer test
```

### Running Tests

```bash
# All tests
./vendor/bin/phpunit

# Specific test
./vendor/bin/phpunit --filter=test_it_proxies_request

# With coverage
./vendor/bin/phpunit --coverage-text
```

## License

MIT License - see [LICENSE](LICENSE) for details.

## Author

Recca Tsai - [recca0120@gmail.com](mailto:recca0120@gmail.com)
