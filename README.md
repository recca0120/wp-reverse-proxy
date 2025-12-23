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
add_filter('reverse_proxy_rules', function ($rules) {
    // Proxy /api/* to backend server
    $rules[] = [
        'source' => '/api/*',
        'target' => 'https://api.example.com',
    ];

    return $rules;
});
```

### Path Rewriting

Rewrite paths when proxying:

```php
add_filter('reverse_proxy_rules', function ($rules) {
    // /api/v1/users → https://backend.com/v1/users
    $rules[] = [
        'source' => '/api/v1/*',
        'target' => 'https://backend.example.com',
        'rewrite' => '/v1/$1',  // $1 = matched wildcard content
    ];

    return $rules;
});
```

### Preserve Original Host Header

Keep the original Host header instead of using the target host:

```php
add_filter('reverse_proxy_rules', function ($rules) {
    $rules[] = [
        'source' => '/api/*',
        'target' => 'https://backend.example.com',
        'preserve_host' => true,
    ];

    return $rules;
});
```

### Multiple Rules

Rules are matched in order (first match wins):

```php
add_filter('reverse_proxy_rules', function ($rules) {
    // More specific rules first
    $rules[] = [
        'source' => '/api/v2/*',
        'target' => 'https://api-v2.example.com',
    ];

    // Fallback for other API routes
    $rules[] = [
        'source' => '/api/*',
        'target' => 'https://api.example.com',
    ];

    return $rules;
});
```

## Rule Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `source` | string | required | URL pattern to match (supports `*` wildcard) |
| `target` | string | required | Target server URL |
| `rewrite` | string | null | Rewrite path pattern (`$1`, `$2` for wildcards) |
| `preserve_host` | bool | false | Keep original Host header |

## Available Hooks

### Filters

| Hook | Parameters | Description |
|------|------------|-------------|
| `reverse_proxy_rules` | `$rules` | Configure proxy rules |
| `reverse_proxy_http_client` | `$client` | Override PSR-18 HTTP client |
| `reverse_proxy_request_body` | `$body` | Override request body |
| `reverse_proxy_response` | `$response, $request` | Modify response before sending |
| `reverse_proxy_should_exit` | `$should_exit` | Control exit behavior |

### Actions

| Hook | Parameters | Description |
|------|------------|-------------|
| `reverse_proxy_error` | `$exception, $request` | Handle proxy errors |
| `reverse_proxy_log` | `$level, $message, $context` | Log proxy events |

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
add_action('reverse_proxy_error', function ($exception, $request) {
    // Send notification, log to external service, etc.
    wp_mail(
        'admin@example.com',
        'Reverse Proxy Error',
        sprintf('Error: %s\nURL: %s', $exception->getMessage(), $request->getUri())
    );
}, 10, 2);
```

### Modify Response

```php
add_filter('reverse_proxy_response', function ($response, $request) {
    // Add custom header to response
    return $response->withHeader('X-Proxied-By', 'WP-Reverse-Proxy');
}, 10, 2);
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
