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

| Middleware | Parameters | Cache | Description |
|------------|------------|:-----:|-------------|
| `ProxyHeaders` | `options` | - | Add X-Forwarded-* headers |
| `SetHost` | `host` | - | Set custom Host header |
| `RewritePath` | `replacement` | - | Rewrite request path (supports $1, $2 capture groups) |
| `RewriteBody` | `replacements` | - | Rewrite response body (regex replacement) |
| `AllowMethods` | `methods...` | - | Restrict HTTP methods |
| `Cors` | `origins, methods, headers, credentials, maxAge` | - | Handle CORS |
| `IpFilter` | `mode, ips...` | - | IP whitelist/blacklist (supports CIDR) |
| `Timeout` | `seconds` | - | Request timeout control |
| `Retry` | `retries, methods, statusCodes` | - | Auto retry on failure |
| `Fallback` | `statusCodes...` | - | Fall back to WordPress |
| `RateLimiting` | `limit, window` | ✓ | Request rate limiting |
| `CircuitBreaker` | `serviceName, threshold, timeout` | ✓ | Circuit breaker pattern |
| `Caching` | `ttl` | ✓ | Response caching |
| `ErrorHandling` | - | - | Catch network errors, return 502 |
| `Logging` | `logger` | - | Log requests/responses (requires PSR-3 Logger) |
| `RequestId` | `header` | - | Add unique request ID |
| `SanitizeHeaders` | - | - | Remove hop-by-hop headers |

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
            "middlewares": ["Cors", "ProxyHeaders"]
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
use Recca0120\ReverseProxy\Routing\RouteCollection;

$routes = new RouteCollection([
    new FileLoader([__DIR__ . '/routes']),
]);

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

### Using Cache

`RateLimiting`, `CircuitBreaker`, `Caching` middlewares require PSR-16 cache:

```php
// Implement PSR-16 CacheInterface or use existing packages (e.g., symfony/cache)
$cache = new YourCacheImplementation();

$routes = new RouteCollection(
    [new FileLoader([__DIR__ . '/routes'])],
    $cache
);

$proxy = new ReverseProxy($routes);
$response = $proxy->handle();
```

### Route Config File Formats

Supports JSON, YAML, PHP formats. Files must contain `routes` key:

**routes.json:**
```json
{
    "routes": [
        {
            "path": "/api/*",
            "target": "https://api.example.com/",
            "methods": ["GET", "POST"],
            "middlewares": ["Cors", "ProxyHeaders", ["RateLimiting", 100, 60]]
        }
    ]
}
```

**routes.yaml:**
```yaml
routes:
  - path: /api/*
    target: https://api.example.com/
    methods: [GET, POST]
    middlewares:
      - Cors
      - ProxyHeaders
      - [RateLimiting, 100, 60]
```

**routes.php:**
```php
<?php
return [
    'routes' => [
        [
            'path' => '/api/*',
            'target' => 'https://api.example.com/',
            'middlewares' => ['Cors', ['RateLimiting', 100, 60]],
        ],
    ],
];
```

### Middleware Configuration Formats

```json
{
    "middlewares": [
        "Cors",
        "ProxyHeaders",
        ["SetHost", "api.example.com"],
        ["RateLimiting", 100, 60],
        ["IpFilter", "allow", "192.168.1.0/24", "10.0.0.1"]
    ]
}
```

Or use colon format (for string configuration):

```
ProxyHeaders|SetHost:api.example.com|Timeout:30|Fallback:404,500
```

### Custom HTTP Client

Default uses `CurlClient` with `['verify' => false, 'decode_content' => false]`.

```php
use Recca0120\ReverseProxy\Http\CurlClient;

$httpClient = new CurlClient([
    'verify' => true,           // Enable SSL verification (default: false)
    'timeout' => 30,            // Timeout in seconds
    'connect_timeout' => 10,    // Connection timeout in seconds
    'decode_content' => true,   // Auto decode gzip/deflate (default: false)
    'proxy' => 'http://proxy:8080', // Proxy server
]);

$proxy = new ReverseProxy($routes, $httpClient);
```

You can also use `StreamClient` (no curl extension required):

```php
use Recca0120\ReverseProxy\Http\StreamClient;

$httpClient = new StreamClient([
    'verify' => true,
    'timeout' => 30,
]);

$proxy = new ReverseProxy($routes, $httpClient);
```

### Programmatic Route Creation

Besides config files, you can create routes programmatically:

```php
use Recca0120\ReverseProxy\ReverseProxy;
use Recca0120\ReverseProxy\Routing\Route;
use Recca0120\ReverseProxy\Routing\RouteCollection;
use Recca0120\ReverseProxy\Middleware\Cors;
use Recca0120\ReverseProxy\Middleware\RateLimiting;

$routes = new RouteCollection();

// Method 1: Pass middlewares to constructor
$routes->add(new Route('/api/*', 'https://api.example.com/', [
    new Cors(),
    new RateLimiting(100, 60),
]));

// Method 2: Chained calls
$routes->add(
    (new Route('POST /users', 'https://api.example.com/'))
        ->middleware(new Cors())
        ->middleware(function ($request, $next) {
            // Custom middleware logic
            return $next($request);
        })
);

$proxy = new ReverseProxy($routes);
```

### Global Middlewares

Middlewares that apply to all routes can be registered via MiddlewareManager:

```php
use Recca0120\ReverseProxy\Routing\RouteCollection;
use Recca0120\ReverseProxy\Routing\MiddlewareManager;
use Recca0120\ReverseProxy\Middleware\SanitizeHeaders;
use Recca0120\ReverseProxy\Middleware\ErrorHandling;

$manager = new MiddlewareManager();
$manager->registerGlobalMiddleware(new SanitizeHeaders());
$manager->registerGlobalMiddleware(new ErrorHandling());

$routes = new RouteCollection(
    [new FileLoader([__DIR__ . '/routes'])],
    $cache,
    $manager
);
```

## License

MIT License - see [LICENSE](LICENSE) for details.

## Author

Recca Tsai - [recca0120@gmail.com](mailto:recca0120@gmail.com)
