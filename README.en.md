# WP Reverse Proxy

[繁體中文](README.md) | **English**

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

## License

MIT License - see [LICENSE](LICENSE) for details.

## Author

Recca Tsai - [recca0120@gmail.com](mailto:recca0120@gmail.com)
