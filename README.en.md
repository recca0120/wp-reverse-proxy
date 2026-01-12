# WP Reverse Proxy

[繁體中文](README.md) | **English**

![Tests](https://github.com/recca0120/wp-reverse-proxy/actions/workflows/tests.yml/badge.svg)
[![codecov](https://codecov.io/gh/recca0120/wp-reverse-proxy/branch/main/graph/badge.svg)](https://codecov.io/gh/recca0120/wp-reverse-proxy)
![PHP Version](https://img.shields.io/badge/PHP-7.2%2B-blue)
![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue)
![License](https://img.shields.io/badge/License-MIT-green)

A WordPress plugin that proxies specific URL paths to external backend servers.

## Features

- Route matching with wildcard (`/api/*`) and prefix matching (`/api`)
- HTTP method matching (`POST /api/users`, `GET|POST /api/*`)
- Nginx-style path prefix stripping (target URL ending with `/`)
- Full HTTP method support (GET, POST, PUT, PATCH, DELETE)
- Request/Response headers forwarding
- Middleware support for request/response processing
- WordPress admin interface (visual route configuration)

## Quick Preview

![Demo](docs/images/demo-add-route.gif)

## Requirements

- PHP 7.2+
- WordPress 5.0+

## Installation

### Download from GitHub Releases (Recommended)

1. Go to the [Releases page](https://github.com/recca0120/wp-reverse-proxy/releases)
2. Download **`reverse-proxy.zip`**
3. In WordPress admin → Plugins → Add New → Upload Plugin
4. Upload the zip file and activate

### Via Composer

```bash
composer require recca0120/wp-reverse-proxy
```

## Quick Start

After activating the plugin, go to **WordPress Admin → Settings → Reverse Proxy** to start configuring routes.

Advanced users can use config files or PHP Filter Hook, see [Configuration Reference](docs/en/configuration.md).

## Documentation

| Document | Description |
|----------|-------------|
| [Configuration Reference](docs/en/configuration.md) | Route config formats, path matching rules |
| [Middleware Reference](docs/en/middlewares.md) | All built-in middlewares and custom middleware |
| [Hooks Reference](docs/en/hooks.md) | WordPress Filters and Actions |
| [Development Guide](docs/en/development.md) | Development setup, testing |

## Built-in Middlewares

| Middleware | Description |
|------------|-------------|
| `ProxyHeaders` | Add X-Forwarded-* headers |
| `SetHost` | Set custom Host header |
| `RewritePath` | Rewrite request path |
| `RewriteBody` | Rewrite response body |
| `AllowMethods` | Restrict HTTP methods |
| `Cors` | Handle CORS |
| `IpFilter` | IP whitelist/blacklist |
| `RateLimiting` | Request rate limiting |
| `Caching` | Response caching |
| `Timeout` | Request timeout control |
| `Retry` | Auto retry on failure |
| `CircuitBreaker` | Circuit breaker pattern |
| `Fallback` | Fall back to WordPress |

See [Middleware Reference](docs/en/middlewares.md) for detailed usage.

## Standalone Usage (Without WordPress)

The core components of this package can be used independently from WordPress, suitable for any PHP project.

### Installation

```bash
composer require recca0120/wp-reverse-proxy
```

### Basic Usage

**1. Create route config file `routes/proxy.json`:**

```json
{
    "routes": [
        {
            "path": "/api/*",
            "target": "https://api.example.com/",
            "middlewares": [
                "Cors",
                ["RateLimiting", 100, 60]
            ]
        }
    ]
}
```

**2. Create entry file:**

```php
<?php

require_once 'vendor/autoload.php';

use Recca0120\ReverseProxy\ReverseProxy;
use Recca0120\ReverseProxy\Routing\FileLoader;
use Recca0120\ReverseProxy\Routing\MiddlewareManager;
use Recca0120\ReverseProxy\Routing\RouteCollection;

// Implement PSR-16 CacheInterface or use existing packages (e.g., symfony/cache)
$cache = new YourCacheImplementation();

// Middleware manager injects cache (required for RateLimiting, CircuitBreaker, Caching)
$middlewareManager = new MiddlewareManager($cache);

// Load route config files from directory
$routes = new RouteCollection(
    [new FileLoader([__DIR__ . '/routes'])],
    $middlewareManager,
    $cache
);

// Handle request
$proxy = new ReverseProxy($routes);
$response = $proxy->handle();

if ($response !== null) {
    http_response_code($response->getStatusCode());
    foreach ($response->getHeaders() as $name => $values) {
        foreach ($values as $value) {
            header("{$name}: {$value}", false);
        }
    }
    echo $response->getBody();
}
```

### Route Config File Formats

Supports JSON and PHP formats:

**routes.json:**
```json
{
    "routes": [
        {
            "path": "/api/*",
            "target": "https://api.example.com/",
            "methods": ["GET", "POST"],
            "middlewares": [
                "Cors",
                "ProxyHeaders",
                ["RateLimiting", 100, 60]
            ]
        }
    ]
}
```

**routes.php:**
```php
<?php
return [
    'routes' => [
        [
            'path' => '/api/*',
            'target' => 'https://api.example.com/',
            'middlewares' => [
                'Cors',
                ['RateLimiting', 100, 60],
            ],
        ],
    ],
];
```

### Custom HTTP Client

Default uses `CurlClient` with `['verify' => false, 'decode_content' => false]`.

```php
use Recca0120\ReverseProxy\Http\CurlClient;

$httpClient = new CurlClient([
    'verify' => true,           // Enable SSL verification (default: false)
    'timeout' => 30,            // Timeout in seconds
    'decode_content' => true,   // Auto decode gzip/deflate (default: false)
]);

$proxy = new ReverseProxy($routes, $httpClient);
```

## License

MIT License - see [LICENSE](LICENSE) for details.

## Author

Recca Tsai - [recca0120@gmail.com](mailto:recca0120@gmail.com)
