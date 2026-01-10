# Hooks Reference

[← Back to main documentation](../../README.en.md)

This document provides detailed documentation for all available WordPress Filters and Actions.

## Filters

| Hook | Parameters | Description |
|------|------------|-------------|
| `reverse_proxy_action_hook` | `$hook` | Set the trigger action hook (default `plugins_loaded`) |
| `reverse_proxy_routes` | `$routes` | Configure proxy routes |
| `reverse_proxy_config_loader` | `$loader` | Override config loader |
| `reverse_proxy_middleware_factory` | `$factory` | Customize middleware factory (register custom aliases) |
| `reverse_proxy_config_directory` | `$directory` | Config file directory (default `WP_CONTENT_DIR/reverse-proxy-routes`) |
| `reverse_proxy_config_pattern` | `$pattern` | Config file pattern (default `*.{json,yaml,yml,php}`) |
| `reverse_proxy_cache` | `$cache` | PSR-16 cache instance (for route caching and middleware injection) |
| `reverse_proxy_route_storage` | `$storage` | Admin route storage implementation (default `OptionsStorage`, can switch to `JsonFileStorage`) |
| `reverse_proxy_default_middlewares` | `$middlewares` | Customize default middlewares |
| `reverse_proxy_psr17_factory` | `$factory` | Override PSR-17 HTTP factory |
| `reverse_proxy_http_client` | `$client` | Override PSR-18 HTTP client |
| `reverse_proxy_request` | `$request` | Override the entire request object (for testing) |
| `reverse_proxy_response` | `$response` | Modify response before sending |
| `reverse_proxy_should_exit` | `$should_exit` | Control exit behavior |

## Actions

| Hook | Parameters | Description |
|------|------------|-------------|
| `reverse_proxy_log` | `$level, $message, $context` | Log proxy events (PSR-3 levels) |

---

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
        return $m instanceof \Recca0120\ReverseProxy\Middleware\ErrorHandling;
    });
});

// Add custom middleware
add_filter('reverse_proxy_default_middlewares', function ($middlewares) {
    $middlewares[] = new MyCustomMiddleware();
    return $middlewares;
});
```

### Switch Route Storage

```php
add_filter('reverse_proxy_route_storage', function() {
    $directory = WP_CONTENT_DIR . '/reverse-proxy-routes';
    return new \Recca0120\ReverseProxy\Routing\JsonFileStorage($directory . '/admin-routes.json');
});
```

### Custom HTTP Client

The plugin uses `CurlClient` (based on cURL extension) by default, with one alternative implementation available:

| Client | Dependency | Description |
|--------|------------|-------------|
| `CurlClient` | curl extension | Default, direct curl usage |
| `StreamClient` | None | Pure PHP, uses `file_get_contents` |

**Available Options:**

| Option | Description | Default | CurlClient | StreamClient |
|--------|-------------|---------|:----------:|:------------:|
| `timeout` | Request timeout (seconds) | `30` | ✅ | ✅ |
| `connect_timeout` | Connection timeout (seconds) | same as timeout | ✅ | ✅ |
| `verify` | SSL certificate verification | `true` | ✅ | ✅ |
| `decode_content` | Auto-decompress response | `true` | ✅ | ✅ |
| `proxy` | Proxy server URL | - | ✅ | ✅ |
| `protocol_version` | HTTP protocol version | `1.1` | ❌ | ✅ |

The plugin uses the following default options for reverse proxying:
- `verify => false` - SSL verification disabled for internal networks
- `decode_content => false` - Preserve original compressed responses

```php
// Customize HTTP client options
add_filter('reverse_proxy_http_client', function () {
    return new \Recca0120\ReverseProxy\Http\CurlClient([
        'timeout' => 60,
        'connect_timeout' => 10,
        'verify' => false,
        'decode_content' => false,
        'proxy' => 'http://proxy.example.com:8080',
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

---

## WordPress Hooks Loading Order

The plugin uses `plugins_loaded` hook instead of `parse_request` to execute before theme loading, improving performance.

| Order | Hook | Available Features | Theme |
|-------|------|-------------------|-------|
| 1 | `mu_plugin_loaded` | Fires as each mu-plugin loads | ❌ |
| 2 | `muplugins_loaded` | All mu-plugins loaded | ❌ |
| 3 | `plugin_loaded` | Fires as each plugin loads | ❌ |
| 4 | **`plugins_loaded`** | **$wpdb, all plugins (WooCommerce)** | ❌ |
| 5 | `setup_theme` | Before theme loads | ❌ |
| 6 | `after_setup_theme` | After theme loads | ✅ |
| 7 | `init` | User authentication ready | ✅ |
| 8 | `wp_loaded` | WordPress fully loaded | ✅ |
| 9 | `parse_request` | Request parsing | ✅ |

### Why `plugins_loaded`?

- **Skip theme loading**: Executes earlier than `parse_request`, no theme loaded
- **Plugins available**: Can use WooCommerce and other plugin features
- **Database available**: `$wpdb` is initialized
- **Performance boost**: Reduces unnecessary WordPress loading

### Need Earlier Execution?

If you don't need other WordPress plugin features, execute directly in mu-plugin (no hook):

```php
<?php
// wp-content/mu-plugins/reverse-proxy-early.php

use Recca0120\ReverseProxy\Routing\Route;
use Recca0120\ReverseProxy\Routing\RouteCollection;

require_once WP_CONTENT_DIR.'/plugins/reverse-proxy/reverse-proxy.php';

// Register routes
add_filter('reverse_proxy_routes', function (RouteCollection $routes) {
    $routes->add(new Route('/api/*', 'https://api.example.com'));

    return $routes;
});

// Execute directly (skip subsequent WordPress loading)
reverse_proxy_handle();
```

This is the fastest execution method, processing requests at the mu-plugin stage.
