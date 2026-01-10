# Hooks 參考

[← 返回主文件](../../README.md)

本文件詳細說明所有可用的 WordPress Filters 和 Actions。

## Filters

| Hook | 參數 | 說明 |
|------|------|------|
| `reverse_proxy_action_hook` | `$hook` | 設定觸發的 action hook（預設 `plugins_loaded`） |
| `reverse_proxy_routes` | `$routes` | 設定代理路由 |
| `reverse_proxy_config_loader` | `$loader` | 覆寫配置載入器 |
| `reverse_proxy_middleware_factory` | `$factory` | 自訂中介層工廠（可註冊自訂別名） |
| `reverse_proxy_config_directory` | `$directory` | 配置檔目錄（預設 `WP_CONTENT_DIR/reverse-proxy-routes`） |
| `reverse_proxy_config_pattern` | `$pattern` | 配置檔匹配模式（預設 `*.{json,yaml,yml,php}`） |
| `reverse_proxy_cache` | `$cache` | PSR-16 快取實例（用於路由快取與中介層注入） |
| `reverse_proxy_route_storage` | `$storage` | 後台路由儲存實作（預設 `OptionsStorage`，可切換為 `JsonFileStorage`） |
| `reverse_proxy_default_middlewares` | `$middlewares` | 自訂預設中介層 |
| `reverse_proxy_psr17_factory` | `$factory` | 覆寫 PSR-17 HTTP 工廠 |
| `reverse_proxy_http_client` | `$client` | 覆寫 PSR-18 HTTP 客戶端 |
| `reverse_proxy_request` | `$request` | 覆寫整個請求物件（用於測試） |
| `reverse_proxy_response` | `$response` | 在發送前修改回應 |
| `reverse_proxy_should_exit` | `$should_exit` | 控制結束行為 |

## Actions

| Hook | 參數 | 說明 |
|------|------|------|
| `reverse_proxy_log` | `$level, $message, $context` | 記錄代理事件（PSR-3 等級） |

---

## 範例

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

### 自訂預設中介層

```php
// 移除所有預設中介層
add_filter('reverse_proxy_default_middlewares', '__return_empty_array');

// 只保留錯誤處理
add_filter('reverse_proxy_default_middlewares', function ($middlewares) {
    return array_filter($middlewares, function ($m) {
        return $m instanceof \Recca0120\ReverseProxy\Middleware\ErrorHandling;
    });
});

// 新增自訂中介層
add_filter('reverse_proxy_default_middlewares', function ($middlewares) {
    $middlewares[] = new MyCustomMiddleware();
    return $middlewares;
});
```

### 切換路由儲存方式

```php
add_filter('reverse_proxy_route_storage', function() {
    $directory = WP_CONTENT_DIR . '/reverse-proxy-routes';
    return new \Recca0120\ReverseProxy\Routing\JsonFileStorage($directory . '/admin-routes.json');
});
```

### 自訂 HTTP 客戶端

外掛預設使用 `CurlClient`（基於 cURL 擴充），另外提供一個替代實作：

| 客戶端 | 依賴 | 說明 |
|--------|------|------|
| `CurlClient` | curl 擴充 | 預設，直接使用 curl |
| `StreamClient` | 無 | 純 PHP，使用 `file_get_contents` |

**可用選項：**

| 選項 | 說明 | 預設值 | CurlClient | StreamClient |
|------|------|--------|:----------:|:------------:|
| `timeout` | 請求逾時（秒） | `30` | ✅ | ✅ |
| `connect_timeout` | 連線逾時（秒） | 同 timeout | ✅ | ✅ |
| `verify` | SSL 憑證驗證 | `true` | ✅ | ✅ |
| `decode_content` | 自動解壓縮回應 | `true` | ✅ | ✅ |
| `proxy` | 代理伺服器 URL | - | ✅ | ✅ |
| `protocol_version` | HTTP 協定版本 | `1.1` | ❌ | ✅ |

外掛使用以下預設選項以適應反向代理：
- `verify => false` - 停用 SSL 驗證（適用於內部網路）
- `decode_content => false` - 保留原始壓縮回應

```php
// 自訂 HTTP 客戶端選項
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

也可使用任何 PSR-18 相容的第三方客戶端（如 Guzzle），需注意禁用自動解壓縮以保持原始內容：

```php
add_filter('reverse_proxy_http_client', function () {
    return new \GuzzleHttp\Client([
        'timeout' => 30,
        'decode_content' => false,
    ]);
});
```

---

## WordPress Hooks 載入順序

外掛使用 `plugins_loaded` hook 而非 `parse_request`，以便在主題載入前執行，提升效能。

| 順序 | Hook | 可用功能 | 主題 |
|-----|------|---------|------|
| 1 | `mu_plugin_loaded` | 每個 mu-plugin 載入時 | ❌ |
| 2 | `muplugins_loaded` | 所有 mu-plugins 載入完成 | ❌ |
| 3 | `plugin_loaded` | 每個一般插件載入時 | ❌ |
| 4 | **`plugins_loaded`** | **$wpdb, 所有插件 (WooCommerce)** | ❌ |
| 5 | `setup_theme` | 主題載入前 | ❌ |
| 6 | `after_setup_theme` | 主題載入後 | ✅ |
| 7 | `init` | 用戶認證完成 | ✅ |
| 8 | `wp_loaded` | WordPress 完全載入 | ✅ |
| 9 | `parse_request` | 解析請求 | ✅ |

### 為什麼選擇 `plugins_loaded`？

- **跳過主題載入**：比 `parse_request` 更早執行，不載入主題
- **插件可用**：可使用 WooCommerce 等插件功能
- **資料庫可用**：`$wpdb` 已初始化
- **效能提升**：減少不必要的 WordPress 載入

### 需要更早執行？

如果不需要其他 WordPress 插件功能，可在 mu-plugin 中直接執行（不使用 hook）：

```php
<?php
// wp-content/mu-plugins/reverse-proxy-early.php

use Recca0120\ReverseProxy\Routing\Route;
use Recca0120\ReverseProxy\Routing\RouteCollection;

require_once WP_CONTENT_DIR.'/plugins/reverse-proxy/reverse-proxy.php';

// 註冊路由
add_filter('reverse_proxy_routes', function (RouteCollection $routes) {
    $routes->add(new Route('/api/*', 'https://api.example.com'));

    return $routes;
});

// 直接執行（跳過後續 WordPress 載入）
reverse_proxy_handle();
```

這是最快的執行方式，在 mu-plugin 階段直接處理請求。
