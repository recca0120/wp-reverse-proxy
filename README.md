# WP Reverse Proxy

**繁體中文** | [English](README.en.md)

一個 WordPress 外掛，可將特定 URL 路徑代理到外部後端伺服器。

## 功能特色

- 路由匹配支援萬用字元 (`/api/*`) 與前綴匹配 (`/api`)
- HTTP 方法匹配 (`POST /api/users`, `GET|POST /api/*`)
- Nginx 風格的路徑前綴移除（目標 URL 以 `/` 結尾）
- 透過中介層重寫路徑
- 完整 HTTP 方法支援 (GET, POST, PUT, PATCH, DELETE)
- 請求/回應標頭轉發
- 中介層支援請求/回應處理
- WordPress 後台管理介面（視覺化路由設定）

## 系統需求

- PHP 7.2+
- WordPress 5.0+

## 安裝

### 從 GitHub Releases 下載（推薦）

1. 前往 [Releases 頁面](https://github.com/recca0120/wp-reverse-proxy/releases)
2. 下載 **`reverse-proxy.zip`**
3. 在 WordPress 後台 → 外掛 → 安裝外掛 → 上傳外掛
4. 上傳 zip 檔案並啟用

### 透過 Composer

```bash
composer require recca0120/wp-reverse-proxy
```

## 快速開始

### 方式一：配置檔（推薦）

在 `wp-content/reverse-proxy-routes/` 目錄建立 JSON 配置檔：

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

### 方式二：PHP Filter Hook

建立 `wp-content/mu-plugins/reverse-proxy-config.php`：

```php
<?php
use Recca0120\ReverseProxy\Routing\Route;
use Recca0120\ReverseProxy\Routing\RouteCollection;

add_filter('reverse_proxy_routes', function (RouteCollection $routes) {
    $routes->add(new Route('/api/*', 'https://api.example.com'));
    return $routes;
});
```

### 方式三：WordPress 後台

WordPress 後台 → 設定 → Reverse Proxy

## 文件

| 文件 | 說明 |
|------|------|
| [配置參考](docs/zh/configuration.md) | 路由配置格式、路徑匹配規則 |
| [中介層參考](docs/zh/middlewares.md) | 所有內建中介層的用法與自訂中介層 |
| [Hooks 參考](docs/zh/hooks.md) | WordPress Filters 和 Actions |
| [開發指南](docs/zh/development.md) | 開發環境設定、測試 |

## 內建中介層

| 中介層 | 說明 |
|--------|------|
| `ProxyHeaders` | 新增 X-Forwarded-* 標頭 |
| `SetHost` | 設定自訂 Host 標頭 |
| `RewritePath` | 重寫請求路徑 |
| `RewriteBody` | 重寫回應內容 |
| `AllowMethods` | 限制 HTTP 方法 |
| `Cors` | 處理 CORS |
| `IpFilter` | IP 白名單/黑名單 |
| `RateLimiting` | 請求限流 |
| `Caching` | 回應快取 |
| `Timeout` | 請求超時控制 |
| `Retry` | 失敗自動重試 |
| `CircuitBreaker` | 熔斷器模式 |
| `Fallback` | 回退給 WordPress 處理 |

詳細用法請參考 [中介層參考](docs/zh/middlewares.md)。

## 實際範例

等同於此 nginx 設定：

```nginx
location ^~ /api/v1 {
    proxy_pass https://127.0.0.1:8080;
    proxy_set_header Host api.example.com;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
}
```

WordPress 對應寫法：

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

## 授權

MIT License - 詳見 [LICENSE](LICENSE)。

## 作者

Recca Tsai - [recca0120@gmail.com](mailto:recca0120@gmail.com)
