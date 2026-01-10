# Configuration Reference

[← Back to main documentation](../../README.en.md)

This document provides detailed documentation for route configuration formats and options.

## Table of Contents

- [Config File Directory](#config-file-directory)
- [Supported File Formats](#supported-file-formats)
- [Full Configuration Example](#full-configuration-example)
- [Middleware Configuration Format](#middleware-configuration-format)
- [Register Custom Middleware Aliases](#register-custom-middleware-aliases)
- [Custom Config Directory](#custom-config-directory)
- [Route Constructor](#route-constructor)
- [Path Matching Behavior](#path-matching-behavior)

---

## Config File Directory

Create JSON, YAML, or PHP config files in `wp-content/reverse-proxy-routes/` directory:

```
wp-content/
├── reverse-proxy-routes/    # Route config directory
│   ├── api.json
│   ├── legacy.yaml
│   └── internal.php
├── plugins/
└── uploads/
```

The plugin automatically loads all `.json`, `.yaml`, `.yml`, and `.php` files from the directory.

---

## Supported File Formats

### JSON Format

```json
{
  "routes": [
    {
      "path": "/api/*",
      "target": "https://api.example.com"
    }
  ]
}
```

### YAML Format

```yaml
routes:
  - path: /legacy/*
    target: https://legacy.example.com
```

### PHP Format

```php
<?php
return [
    'routes' => [
        [
            'path' => '/internal/*',
            'target' => 'https://internal.example.com',
        ],
    ],
];
```

---

## Full Configuration Example

### JSON Format

```json
{
  "routes": [
    {
      "path": "/api/v2/*",
      "target": "https://api-v2.example.com",
      "methods": ["GET", "POST"],
      "middlewares": [
        "ProxyHeaders",
        ["SetHost", "api.example.com"],
        ["Timeout", 30],
        { "name": "RateLimiting", "options": { "limit": 100, "window": 60 } }
      ]
    },
    {
      "path": "/legacy/*",
      "target": "https://legacy.example.com"
    }
  ]
}
```

### YAML Format (supports Anchors & Aliases)

```yaml
# Define shared config with anchors
defaults: &defaults
  middlewares:
    - ProxyHeaders
    - SetHost: api.example.com

routes:
  - path: /api/v2/*
    target: https://api-v2.example.com
    methods: [GET, POST]
    middlewares:
      - ProxyHeaders
      - SetHost: api.example.com
      - Timeout: 30
      - name: RateLimiting
        options:
          limit: 100
          window: 60

  - path: /legacy/*
    target: https://legacy.example.com
    <<: *defaults  # Merge shared config
```

### PHP Format

```php
<?php
return [
    'routes' => [
        [
            'path' => '/api/v2/*',
            'target' => 'https://api-v2.example.com',
            'methods' => ['GET', 'POST'],
            'middlewares' => [
                'ProxyHeaders',
                'SetHost' => 'api.example.com',      // Key-Value format
                'Timeout' => 30,
                'RateLimiting' => ['limit' => 100, 'window' => 60],
            ],
        ],
        [
            'path' => '/legacy/*',
            'target' => 'https://legacy.example.com',
        ],
    ],
];
```

---

## Middleware Configuration Format

Multiple formats supported, can be mixed:

| Format | Description | Example |
|--------|-------------|---------|
| String | Middleware without arguments | `"ProxyHeaders"` |
| Colon format | `name:param` | `"SetHost:api.example.com"` |
| Colon multi-params | `name:param1,param2` | `"RateLimiting:100,60"` |
| Pipe string | Multiple middlewares with `\|` | `"ProxyHeaders\|Timeout:30"` |
| Array | `[name, args...]` | `["SetHost", "api.example.com"]` |
| Key-Value | `name => param` | `"SetHost" => "api.example.com"` |
| Object | Full format | `{ "name": "RateLimiting", "options": {...} }` |

### Equivalent Examples

```php
// The following are all equivalent
'middlewares' => 'ProxyHeaders|SetHost:api.example.com|Timeout:30'

'middlewares' => [
    'ProxyHeaders',
    'SetHost:api.example.com',
    'Timeout:30',
]

// Key-Value format
'middlewares' => [
    'ProxyHeaders',
    'SetHost' => 'api.example.com',
    'Timeout' => 30,
]

'middlewares' => [
    ['name' => 'ProxyHeaders'],
    ['name' => 'SetHost', 'options' => 'api.example.com'],
    ['name' => 'Timeout', 'options' => 30],
]
```

### Object Format Fields

| Field | Description | Example |
|-------|-------------|---------|
| `name` | Middleware name (alias or full class name) | `"ProxyHeaders"` |
| `args` | Positional arguments array | `["example.com"]` |
| `options` | Single value or named parameters | `30` or `{"limit": 100}` |

### Available Middleware Aliases

`ProxyHeaders`, `SetHost`, `RewritePath`, `RewriteBody`, `AllowMethods`, `Cors`, `IpFilter`, `RateLimiting`, `Caching`, `RequestId`, `Retry`, `CircuitBreaker`, `Timeout`, `Fallback`, `Logging`, `ErrorHandling`, `SanitizeHeaders`

---

## Register Custom Middleware Aliases

```php
// mu-plugins/reverse-proxy.php
use Recca0120\ReverseProxy\Routing\MiddlewareManager;

// Option 1: Register one by one
MiddlewareManager::registerAlias('MyAuth', MyAuthMiddleware::class);
MiddlewareManager::registerAlias('CustomCache', MyCacheMiddleware::class);

// Option 2: Batch registration (array format)
MiddlewareManager::registerAlias([
    'MyAuth' => MyAuthMiddleware::class,
    'CustomCache' => MyCacheMiddleware::class,
    'RateLimit' => MyRateLimitMiddleware::class,
]);
```

Then use in config files:

```json
{
  "routes": [{
    "path": "/api/*",
    "target": "https://api.example.com",
    "middlewares": [
      { "name": "MyAuth" },
      { "name": "CustomCache", "options": { "ttl": 300 } }
    ]
  }]
}
```

---

## Custom Routes Directory

```php
// Change routes config directory
add_filter('reverse_proxy_routes_directory', function () {
    return WP_CONTENT_DIR . '/my-proxy-routes';
});
```

---

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

---

## Path Matching Behavior

### Prefix Matching (Default Behavior)

Paths without wildcards automatically perform prefix matching:

```php
// /api matches /api, /api/, /api/users, /api/users/123
new Route('/api', 'https://backend.example.com');
```

### Wildcard Matching

The `*` wildcard matches any characters:

```php
// /api/* matches /api, /api/, /api/users, /api/users/123
new Route('/api/*', 'https://backend.example.com');

// /api/*/posts matches /api/users/posts, /api/123/posts
// but NOT /api/posts or /api/users/posts/123
new Route('/api/*/posts', 'https://backend.example.com');
```

> **Note**: `/api` is equivalent to `/api/*`, both perform prefix matching.

### Nginx-Style Path Prefix Stripping

When the target URL ends with `/`, the matched path prefix is automatically stripped (similar to nginx `proxy_pass` behavior):

```php
// Target URL without trailing /: keep full path
// /api/users/123 → https://backend.example.com/api/users/123
new Route('/api/*', 'https://backend.example.com');

// Target URL with trailing /: strip /api prefix
// /api/users/123 → https://backend.example.com/users/123
new Route('/api/*', 'https://backend.example.com/');
```

**Nginx Configuration Comparison:**

```nginx
# Keep full path (no trailing /)
location /api/ {
    proxy_pass https://backend.example.com;
}
# /api/users → https://backend.example.com/api/users

# Strip prefix (with trailing /)
location /api/ {
    proxy_pass https://backend.example.com/;
}
# /api/users → https://backend.example.com/users
```

### Method-Based Routing Example

Route different HTTP methods to different backends:

```php
use Recca0120\ReverseProxy\Routing\Route;
use Recca0120\ReverseProxy\Routing\RouteCollection;

add_filter('reverse_proxy_routes', function (RouteCollection $routes) {
    $routes->add([
        // Read operations → read replica
        new Route('GET /api/users/*', 'https://read-replica.example.com'),
        // Write operations → primary server
        new Route('POST|PUT|DELETE /api/users/*', 'https://primary.example.com'),
    ]);

    return $routes;
});
```
