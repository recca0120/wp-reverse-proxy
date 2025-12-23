# WP Reverse Proxy

A WordPress plugin that proxies specific URL paths to external backend servers.

## Features

- Route matching with wildcard support (`/api/*`)
- Path rewriting (`/api/v1/*` → `/v1/$1`)
- Full HTTP method support (GET, POST, PUT, PATCH, DELETE)
- Request/Response headers forwarding
- Query string preservation
- Host header configuration
- Error handling with 502 Bad Gateway
- Logging integration
- PSR-18 compliant HTTP client

## Requirements

- PHP 7.2+
- WordPress 5.0+

## Installation

### Via Composer

```bash
composer require recca0120/wp-reverse-proxy
```

### Manual Installation

1. Download the plugin
2. Upload to `/wp-content/plugins/reverse-proxy/`
3. Run `composer install` in the plugin directory
4. Activate the plugin in WordPress admin

## Usage

### Basic Configuration

Add proxy rules in your theme's `functions.php` or a custom plugin:

```php
use ReverseProxy\Rule;

add_filter('reverse_proxy_rules', function () {
    return [
        // Proxy /api/* to backend server
        new Rule('/api/*', 'https://api.example.com'),
    ];
});
```

### Path Rewriting

Rewrite paths when proxying:

```php
use ReverseProxy\Rule;

add_filter('reverse_proxy_rules', function () {
    return [
        // /api/v1/users → https://backend.com/v1/users
        // $1 = matched wildcard content
        new Rule('/api/v1/*', 'https://backend.example.com', '/v1/$1'),
    ];
});
```

### Preserve Original Host Header

Keep the original Host header instead of using the target host:

```php
use ReverseProxy\Rule;

add_filter('reverse_proxy_rules', function () {
    return [
        new Rule('/api/*', 'https://backend.example.com', null, true),
    ];
});
```

### Multiple Rules

Rules are matched in order (first match wins):

```php
use ReverseProxy\Rule;

add_filter('reverse_proxy_rules', function () {
    return [
        // More specific rules first
        new Rule('/api/v2/*', 'https://api-v2.example.com'),
        // Fallback for other API routes
        new Rule('/api/*', 'https://api.example.com'),
    ];
});
```

## Rule Constructor

```php
new Rule(
    string $source,         // URL pattern to match (supports `*` wildcard)
    string $target,         // Target server URL
    ?string $rewrite,       // Rewrite path pattern (`$1`, `$2` for wildcards)
    bool $preserveHost      // Keep original Host header (default: false)
);
```

## Middleware

Rules support middleware for request/response processing:

```php
use ReverseProxy\Rule;

$rule = (new Rule('/api/*', 'https://backend.example.com'))
    ->middleware(function ($request, $next) {
        // Before: modify request
        $request = $request->withHeader('Authorization', 'Bearer token');

        // Execute next middleware or proxy
        $response = $next($request);

        // After: modify response
        return $response->withHeader('X-Processed', 'true');
    });
```

### Middleware Examples

**Add Authentication Header:**
```php
->middleware(function ($request, $next) {
    return $next($request->withHeader('Authorization', 'Bearer ' . get_api_token()));
})
```

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

**Chain Multiple Middlewares:**
```php
$rule = (new Rule('/api/*', 'https://backend.example.com'))
    ->middleware($authMiddleware)
    ->middleware($cacheMiddleware)
    ->middleware($loggingMiddleware);
```

Middlewares execute in onion-style order:
```
Request → [MW1 → [MW2 → [MW3 → Proxy] ← MW3] ← MW2] ← MW1 → Response
```

## Available Hooks

### Filters

| Hook | Parameters | Description |
|------|------------|-------------|
| `reverse_proxy_rules` | `$rules` | Configure proxy rules |
| `reverse_proxy_http_client` | `$client` | Override PSR-18 HTTP client |
| `reverse_proxy_request_body` | `$body` | Override request body (for testing) |
| `reverse_proxy_response` | `$response` | Modify response before sending |
| `reverse_proxy_should_exit` | `$should_exit` | Control exit behavior |

### Actions

| Hook | Parameters | Description |
|------|------------|-------------|
| `reverse_proxy_log` | `$level, $message, $context` | Log proxy events (PSR-3 levels) |

## Examples

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
        // Send notification, log to external service, etc.
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
    // Add custom header to response
    return $response->withHeader('X-Proxied-By', 'WP-Reverse-Proxy');
});
```

### Custom HTTP Client

```php
add_filter('reverse_proxy_http_client', function ($client) {
    // Use custom PSR-18 client (e.g., Guzzle)
    return new \GuzzleHttp\Client();
});
```

## How It Works

1. Plugin hooks into WordPress `parse_request` action
2. Checks if request path matches any configured rule
3. If matched:
   - Forwards request to target server
   - Returns response to client
   - Exits (stops WordPress processing)
4. If not matched:
   - WordPress continues normal request handling

```
Request → parse_request → Match Rule?
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
