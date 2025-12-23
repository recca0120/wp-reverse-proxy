# WP Reverse Proxy

**繁體中文** | [English](README.en.md)

一個 WordPress 外掛，可將特定 URL 路徑代理到外部後端伺服器。

## 功能特色

- 路由匹配支援萬用字元 (`/api/*`)
- HTTP 方法匹配 (`POST /api/users`, `GET|POST /api/*`)
- 透過中介層重寫路徑
- 完整 HTTP 方法支援 (GET, POST, PUT, PATCH, DELETE)
- 請求/回應標頭轉發
- 查詢字串保留
- 可自訂 Host 標頭
- 標準代理標頭 (X-Real-IP, X-Forwarded-For 等)
- 錯誤處理與 502 Bad Gateway
- 日誌整合
- PSR-18 相容 HTTP 客戶端
- 中介層支援請求/回應處理

## 系統需求

- PHP 7.2+
- WordPress 5.0+

## 安裝

### 透過 Composer

```bash
composer require recca0120/wp-reverse-proxy
```

### 手動安裝

1. 下載外掛
2. 上傳至 `/wp-content/plugins/reverse-proxy/`
3. 在外掛目錄執行 `composer install`
4. 在 WordPress 後台啟用外掛

## 使用方式

### 基本設定

建立設定檔 `wp-content/mu-plugins/reverse-proxy-config.php`：

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

> **注意**：`mu-plugins` 資料夾預設不存在，需手動建立。

> **為什麼使用 mu-plugins？**
> - 自動載入，不需在後台啟用
> - 不受佈景主題影響
> - 比一般外掛更早載入
> - 適合基礎設施層級的設定
>
> 詳見 [WordPress MU-Plugins 文件](https://developer.wordpress.org/advanced-administration/plugins/mu-plugins/)

**其他設定位置**（不建議）：
- `functions.php` - 換主題會失效
- 自訂外掛 - 可能被停用

### 多個路由

路由依序匹配（第一個符合的優先）：

```php
use ReverseProxy\Route;

add_filter('reverse_proxy_routes', function () {
    return [
        // 更具體的路由放前面
        new Route('/api/v2/*', 'https://api-v2.example.com'),
        // 其他 API 路由的備援
        new Route('/api/*', 'https://api.example.com'),
    ];
});
```

### 搭配中介層

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

### 基於方法的路由範例

將不同 HTTP 方法導向不同後端：

```php
use ReverseProxy\Route;

add_filter('reverse_proxy_routes', function () {
    return [
        // 讀取操作 → 讀取副本
        new Route('GET /api/users/*', 'https://read-replica.example.com'),
        // 寫入操作 → 主要伺服器
        new Route('POST|PUT|DELETE /api/users/*', 'https://primary.example.com'),
    ];
});
```

## 內建中介層

### ProxyHeadersMiddleware

為轉發請求新增標準代理標頭：

```php
use ReverseProxy\Middleware\ProxyHeadersMiddleware;

new Route('/api/*', 'https://backend.example.com', [
    new ProxyHeadersMiddleware(),
]);
```

新增的標頭：
- `X-Real-IP` - 客戶端 IP 位址
- `X-Forwarded-For` - 代理 IP 鏈
- `X-Forwarded-Proto` - 原始協定 (http/https)
- `X-Forwarded-Port` - 原始連接埠

### SetHostMiddleware

設定自訂 Host 標頭：

```php
use ReverseProxy\Middleware\SetHostMiddleware;

new Route('/api/*', 'https://127.0.0.1:8080', [
    new SetHostMiddleware('api.example.com'),
]);
```

### RewritePathMiddleware

重寫請求路徑：

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

// 多個萬用字元：/api/users/posts/123 → /v2/users/items/123
new Route('/api/*/posts/*', 'https://backend.example.com', [
    new RewritePathMiddleware('/api/*/posts/*', '/v2/$1/items/$2'),
]);
```

### AllowMethodsMiddleware

限制允許的 HTTP 方法，其他方法回傳 405 Method Not Allowed：

```php
use ReverseProxy\Middleware\AllowMethodsMiddleware;

new Route('/api/*', 'https://backend.example.com', [
    new AllowMethodsMiddleware(['GET', 'POST']),
]);
```

功能：
- 回傳 405 並在 `Allow` 標頭列出允許的方法
- CORS 預檢請求的 OPTIONS 總是允許
- 方法匹配不區分大小寫

**Route 方法 vs AllowMethodsMiddleware：**

| 面向 | Route 方法 (`POST /api/*`) | AllowMethodsMiddleware |
|------|---------------------------|------------------------|
| 不符合時的行為 | 跳至下一個路由 | 回傳 405 回應 |
| 使用場景 | 導向不同後端 | 限制路由允許的方法 |

## 自訂中介層

### 使用閉包

```php
$route = (new Route('/api/*', 'https://backend.example.com'))
    ->middleware(function ($request, $next) {
        // 之前：修改請求
        $request = $request->withHeader('Authorization', 'Bearer token');

        // 執行下一個中介層或代理
        $response = $next($request);

        // 之後：修改回應
        return $response->withHeader('X-Processed', 'true');
    });
```

### 使用 MiddlewareInterface

建立可重複使用的中介層類別：

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

// 使用方式
$route = (new Route('/api/*', 'https://backend.example.com'))
    ->middleware(new AddAuthHeader('my-secret-token'));
```

### 中介層範例

**快取回應：**
```php
->middleware(function ($request, $next) {
    $cacheKey = md5((string) $request->getUri());

    if ($cached = wp_cache_get($cacheKey, 'proxy')) {
        return $cached;  // 短路：跳過代理
    }

    $response = $next($request);
    wp_cache_set($cacheKey, $response, 'proxy', 300);

    return $response;
})
```

**測量請求時間：**
```php
->middleware(function ($request, $next) {
    $start = microtime(true);
    $response = $next($request);
    $elapsed = microtime(true) - $start;

    return $response->withHeader('X-Response-Time', round($elapsed * 1000) . 'ms');
})
```

### 串接中介層

使用流暢 API：
```php
$route = (new Route('/api/*', 'https://backend.example.com'))
    ->middleware(new ProxyHeadersMiddleware())
    ->middleware(new SetHostMiddleware('api.example.com'))
    ->middleware($authMiddleware)
    ->middleware($loggingMiddleware);
```

或透過建構子：
```php
$route = new Route('/api/*', 'https://backend.example.com', [
    new ProxyHeadersMiddleware(),
    new SetHostMiddleware('api.example.com'),
    $authMiddleware,
    $loggingMiddleware,
]);
```

中介層以洋蔥式順序執行：
```
Request → [MW1 → [MW2 → [MW3 → Proxy] ← MW3] ← MW2] ← MW1 → Response
```

## 實際範例

等同於此 nginx 設定：

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

WordPress 對應寫法：

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

## 可用 Hooks

### Filters

| Hook | 參數 | 說明 |
|------|------|------|
| `reverse_proxy_routes` | `$routes` | 設定代理路由 |
| `reverse_proxy_http_client` | `$client` | 覆寫 PSR-18 HTTP 客戶端 |
| `reverse_proxy_request_body` | `$body` | 覆寫請求主體（用於測試） |
| `reverse_proxy_response` | `$response` | 在發送前修改回應 |
| `reverse_proxy_should_exit` | `$should_exit` | 控制結束行為 |

### Actions

| Hook | 參數 | 說明 |
|------|------|------|
| `reverse_proxy_log` | `$level, $message, $context` | 記錄代理事件（PSR-3 等級） |

## Hook 範例

### 日誌記錄

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

### 錯誤處理

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

### 修改回應

```php
add_filter('reverse_proxy_response', function ($response) {
    return $response->withHeader('X-Proxied-By', 'WP-Reverse-Proxy');
});
```

### 自訂 HTTP 客戶端

```php
add_filter('reverse_proxy_http_client', function ($client) {
    return new \GuzzleHttp\Client();
});
```

## 運作原理

1. 外掛掛載到 WordPress `parse_request` action
2. 檢查請求路徑是否符合任何已設定的路由
3. 如果符合：
   - 轉發請求到目標伺服器
   - 回傳回應給客戶端
   - 結束（停止 WordPress 處理）
4. 如果不符合：
   - WordPress 繼續正常處理請求

```
Request → parse_request → 符合路由？
                              ↓
                    是 ──→ 代理 → 回應 → 結束
                              ↓
                    否 ──→ WordPress 正常處理
```

## 開發

### 設定

```bash
# 安裝依賴
composer install

# 設定 WordPress 測試環境
./bin/install-wp-tests.sh

# 執行測試
composer test
```

### 執行測試

```bash
# 所有測試
./vendor/bin/phpunit

# 特定測試
./vendor/bin/phpunit --filter=test_it_proxies_request

# 含覆蓋率
./vendor/bin/phpunit --coverage-text
```

## 授權

MIT License - 詳見 [LICENSE](LICENSE)。

## 作者

Recca Tsai - [recca0120@gmail.com](mailto:recca0120@gmail.com)
