# WP Reverse Proxy

**繁體中文** | [English](README.en.md)

![Tests](https://github.com/recca0120/wp-reverse-proxy/actions/workflows/tests.yml/badge.svg)
[![codecov](https://codecov.io/gh/recca0120/wp-reverse-proxy/branch/main/graph/badge.svg)](https://codecov.io/gh/recca0120/wp-reverse-proxy)
![PHP Version](https://img.shields.io/badge/PHP-7.2%2B-blue)
![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue)
![License](https://img.shields.io/badge/License-MIT-green)

一個 WordPress 外掛，可將特定 URL 路徑代理到外部後端伺服器。

## 功能特色

- 路由匹配支援萬用字元 (`/api/*`) 與前綴匹配 (`/api`)
- HTTP 方法匹配 (`POST /api/users`, `GET|POST /api/*`)
- Nginx 風格的路徑前綴移除（目標 URL 以 `/` 結尾）
- 完整 HTTP 方法支援 (GET, POST, PUT, PATCH, DELETE)
- 請求/回應標頭轉發
- 中介層支援請求/回應處理
- WordPress 後台管理介面（視覺化路由設定）

## 快速預覽

![Demo](docs/images/demo-add-route.gif)

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

啟用外掛後，前往 **WordPress 後台 → 設定 → Reverse Proxy** 即可開始設定路由。

進階用戶可使用配置檔或 PHP Filter Hook，詳見 [配置參考](docs/zh/configuration.md)。

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

## 獨立使用（不依賴 WordPress）

這個套件的核心元件可以獨立於 WordPress 使用，適用於任何 PHP 專案。

### 安裝

```bash
composer require recca0120/wp-reverse-proxy
```

### 基本用法

**1. 建立路由設定檔 `routes/proxy.json`：**

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

**2. 建立入口檔案：**

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

### 使用快取

若使用 `RateLimiting`、`CircuitBreaker`、`Caching` 等中介層，需要注入 PSR-16 快取：

```php
use Recca0120\ReverseProxy\ReverseProxy;
use Recca0120\ReverseProxy\Routing\FileLoader;
use Recca0120\ReverseProxy\Routing\RouteCollection;

// 實作 PSR-16 CacheInterface 或使用現有套件（如 symfony/cache）
$cache = new YourCacheImplementation();

$routes = new RouteCollection(
    [new FileLoader([__DIR__ . '/routes'])],
    $cache
);

$proxy = new ReverseProxy($routes);
$response = $proxy->handle();
```

### 路由設定檔格式

支援 JSON、YAML、PHP 格式，檔案須包含 `routes` 鍵：

**routes.json：**
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

**routes.php：**
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

### 自訂 HTTP Client

預設使用 `CurlClient`，參數為 `['verify' => false, 'decode_content' => false]`。

```php
use Recca0120\ReverseProxy\Http\CurlClient;

$httpClient = new CurlClient([
    'verify' => true,           // 啟用 SSL 驗證（預設 false）
    'timeout' => 30,            // 超時秒數
    'decode_content' => true,   // 自動解碼 gzip/deflate（預設 false）
]);

$proxy = new ReverseProxy($routes, $httpClient);
```

## 授權

MIT License - 詳見 [LICENSE](LICENSE)。

## 作者

Recca Tsai - [recca0120@gmail.com](mailto:recca0120@gmail.com)
