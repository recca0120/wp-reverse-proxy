# 配置參考

[← 返回主文件](../../README.md)

本文件詳細說明路由配置的各種格式與選項。

## 目錄

- [配置檔目錄](#配置檔目錄)
- [支援的檔案格式](#支援的檔案格式)
- [完整配置範例](#完整配置範例)
- [中介層配置格式](#中介層配置格式)
- [註冊自訂中介層別名](#註冊自訂中介層別名)
- [自訂配置目錄](#自訂配置目錄)
- [Route 建構子](#route-建構子)
- [路徑匹配行為](#路徑匹配行為)

---

## 配置檔目錄

在 `wp-content/reverse-proxy-routes/` 目錄建立 JSON、YAML 或 PHP 配置檔：

```
wp-content/
├── reverse-proxy-routes/    # 路由配置目錄
│   ├── api.json
│   ├── legacy.yaml
│   └── internal.php
├── plugins/
└── uploads/
```

外掛會自動載入目錄下所有 `.json`、`.yaml`、`.yml` 和 `.php` 檔案。

---

## 支援的檔案格式

### JSON 格式

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

### YAML 格式

```yaml
routes:
  - path: /legacy/*
    target: https://legacy.example.com
```

### PHP 格式

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

## 完整配置範例

### JSON 格式

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

### YAML 格式（支援 Anchors & Aliases）

```yaml
# 使用 anchors 定義共用設定
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
    <<: *defaults  # 合併共用設定
```

### PHP 格式

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
                'SetHost' => 'api.example.com',      // Key-Value 格式
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

## 中介層配置格式

支援多種格式，可混合使用：

| 格式 | 說明 | 範例 |
|------|------|------|
| 字串 | 無參數中介層 | `"ProxyHeaders"` |
| 冒號格式 | `名稱:參數` | `"SetHost:api.example.com"` |
| 冒號多參數 | `名稱:參數1,參數2` | `"RateLimiting:100,60"` |
| Pipe 字串 | 多個中介層用 `\|` 分隔 | `"ProxyHeaders\|Timeout:30"` |
| 陣列 | `[名稱, 參數...]` | `["SetHost", "api.example.com"]` |
| Key-Value | `名稱 => 參數` | `"SetHost" => "api.example.com"` |
| 物件 | 完整格式 | `{ "name": "RateLimiting", "options": {...} }` |

### 範例對照

```php
// 以下四種寫法等效
'middlewares' => 'ProxyHeaders|SetHost:api.example.com|Timeout:30'

'middlewares' => [
    'ProxyHeaders',
    'SetHost:api.example.com',
    'Timeout:30',
]

// Key-Value 格式
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

### 物件格式欄位

| 欄位 | 說明 | 範例 |
|------|------|------|
| `name` | 中介層名稱（別名或完整類別名） | `"ProxyHeaders"` |
| `args` | 位置參數陣列 | `["example.com"]` |
| `options` | 單一值或具名參數 | `30` 或 `{"limit": 100}` |

### 可用的中介層別名

`ProxyHeaders`, `SetHost`, `RewritePath`, `RewriteBody`, `AllowMethods`, `Cors`, `IpFilter`, `RateLimiting`, `Caching`, `RequestId`, `Retry`, `CircuitBreaker`, `Timeout`, `Fallback`, `Logging`, `ErrorHandling`, `SanitizeHeaders`

---

## 註冊自訂中介層別名

```php
// mu-plugins/reverse-proxy.php
use Recca0120\ReverseProxy\Routing\MiddlewareManager;

// 方式一：逐一註冊
MiddlewareManager::registerAlias('MyAuth', MyAuthMiddleware::class);
MiddlewareManager::registerAlias('CustomCache', MyCacheMiddleware::class);

// 方式二：批次註冊（陣列格式）
MiddlewareManager::registerAlias([
    'MyAuth' => MyAuthMiddleware::class,
    'CustomCache' => MyCacheMiddleware::class,
    'RateLimit' => MyRateLimitMiddleware::class,
]);
```

然後在配置檔中使用：

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

## 自訂配置目錄

```php
// 變更配置檔目錄
add_filter('reverse_proxy_config_directory', function () {
    return WP_CONTENT_DIR . '/my-proxy-routes';
});

// 變更檔案匹配模式（預設 *.{json,yaml,yml,php}）
add_filter('reverse_proxy_config_pattern', function () {
    return '*.routes.json';
});
```

---

## Route 建構子

```php
new Route(
    string $source,       // 要匹配的 URL 模式（支援 `*` 萬用字元和 HTTP 方法）
    string $target,       // 目標伺服器 URL
    array $middlewares    // 選用：中介層陣列
);
```

### Source 模式格式

**僅路徑**（匹配所有 HTTP 方法）：
```php
new Route('/api/*', 'https://backend.example.com');
```

**單一 HTTP 方法**：
```php
new Route('POST /api/users', 'https://backend.example.com');
new Route('DELETE /api/users/*', 'https://backend.example.com');
```

**多個 HTTP 方法**（使用 `|` 分隔）：
```php
new Route('GET|POST /api/users', 'https://backend.example.com');
new Route('GET|POST|PUT|DELETE /api/*', 'https://backend.example.com');
```

---

## 路徑匹配行為

### 前綴匹配（預設行為）

沒有萬用字元的路徑會自動進行前綴匹配：

```php
// /api 會匹配 /api, /api/, /api/users, /api/users/123
new Route('/api', 'https://backend.example.com');
```

### 萬用字元匹配

`*` 萬用字元可匹配任意字元：

```php
// /api/* 會匹配 /api, /api/, /api/users, /api/users/123
new Route('/api/*', 'https://backend.example.com');

// /api/*/posts 會匹配 /api/users/posts, /api/123/posts
// 但不會匹配 /api/posts 或 /api/users/posts/123
new Route('/api/*/posts', 'https://backend.example.com');
```

> **注意**：`/api` 等同於 `/api/*`，兩者都會進行前綴匹配。

### Nginx 風格的路徑前綴移除

當目標 URL 以 `/` 結尾時，會自動移除匹配的路徑前綴（類似 nginx 的 `proxy_pass` 行為）：

```php
// 目標 URL 不以 / 結尾：保留完整路徑
// /api/users/123 → https://backend.example.com/api/users/123
new Route('/api/*', 'https://backend.example.com');

// 目標 URL 以 / 結尾：移除 /api 前綴
// /api/users/123 → https://backend.example.com/users/123
new Route('/api/*', 'https://backend.example.com/');
```

**對照 nginx 設定：**

```nginx
# 保留完整路徑（不以 / 結尾）
location /api/ {
    proxy_pass https://backend.example.com;
}
# /api/users → https://backend.example.com/api/users

# 移除前綴（以 / 結尾）
location /api/ {
    proxy_pass https://backend.example.com/;
}
# /api/users → https://backend.example.com/users
```

### 基於方法的路由範例

將不同 HTTP 方法導向不同後端：

```php
use Recca0120\ReverseProxy\Routing\Route;
use Recca0120\ReverseProxy\Routing\RouteCollection;

add_filter('reverse_proxy_routes', function (RouteCollection $routes) {
    $routes->add([
        // 讀取操作 → 讀取副本
        new Route('GET /api/users/*', 'https://read-replica.example.com'),
        // 寫入操作 → 主要伺服器
        new Route('POST|PUT|DELETE /api/users/*', 'https://primary.example.com'),
    ]);

    return $routes;
});
```
