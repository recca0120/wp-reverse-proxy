# WP Reverse Proxy

[繁體中文](README.md) | **English**

A WordPress plugin that proxies specific URL paths to external backend servers.

## Features

- Route matching with wildcard (`/api/*`) and prefix matching (`/api`)
- HTTP method matching (`POST /api/users`, `GET|POST /api/*`)
- Nginx-style path prefix stripping (target URL ending with `/`)
- Path rewriting via middleware
- Full HTTP method support (GET, POST, PUT, PATCH, DELETE)
- Request/Response headers forwarding
- Middleware support for request/response processing
- WordPress admin interface (visual route configuration)

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

### Option 1: Config Files (Recommended)

Create a JSON config file in `wp-content/reverse-proxy-routes/`:

```json
{
  "routes": [
    {
      "path": "/api/*",
      "target": "https://api.example.com",
      "middlewares": ["ProxyHeaders"]
    }
  ]
}
```

### Option 2: PHP Filter Hook

Create `wp-content/mu-plugins/reverse-proxy-config.php`:

```php
<?php
use Recca0120\ReverseProxy\Routing\Route;
use Recca0120\ReverseProxy\Routing\RouteCollection;

add_filter('reverse_proxy_routes', function (RouteCollection $routes) {
    $routes->add(new Route('/api/*', 'https://api.example.com'));
    return $routes;
});
```

### Option 3: WordPress Admin

WordPress Admin → Settings → Reverse Proxy

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

## Real-World Example

Equivalent to this nginx configuration:

```nginx
location ^~ /api/v1 {
    proxy_pass https://127.0.0.1:8080;
    proxy_set_header Host api.example.com;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
}
```

WordPress equivalent:

```php
use Recca0120\ReverseProxy\Routing\Route;
use Recca0120\ReverseProxy\Middleware\ProxyHeaders;
use Recca0120\ReverseProxy\Middleware\SetHost;

add_filter('reverse_proxy_routes', function ($routes) {
    $routes->add(new Route('/api/v1/*', 'https://127.0.0.1:8080', [
        new ProxyHeaders(),
        new SetHost('api.example.com'),
    ]));
    return $routes;
});
```

## License

MIT License - see [LICENSE](LICENSE) for details.

## Author

Recca Tsai - [recca0120@gmail.com](mailto:recca0120@gmail.com)
