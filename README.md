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

### 從 GitHub Releases 下載（推薦）

1. 前往 [Releases 頁面](https://github.com/recca0120/wp-reverse-proxy/releases)
2. 下載 **`reverse-proxy.zip`**（使用 prefixed namespace 避免衝突）
3. 在 WordPress 後台 → 外掛 → 安裝外掛 → 上傳外掛
4. 上傳 zip 檔案並啟用

### 透過 Composer

```bash
composer require recca0120/wp-reverse-proxy
```

### 手動安裝（開發用）

1. Clone 專案至 `/wp-content/plugins/wp-reverse-proxy/`
2. 在外掛目錄執行 `composer install`
3. 在 WordPress 後台啟用外掛

## 正式版 vs 開發版

本外掛使用 [Strauss](https://github.com/BrianHenryIE/strauss) 來處理第三方依賴的 namespace，避免與其他 WordPress 外掛的依賴衝突。

| 版本 | 來源 | Namespace | 適用情境 |
|------|------|-----------|----------|
| **正式版** | GitHub Releases ZIP | `Recca0120\ReverseProxy\Vendor\*` | 一般使用 |
| **開發版** | Git clone + composer | 原始 namespace（如 `Psr\*`） | 開發/貢獻 |

**為什麼需要 Strauss？**

如果兩個 WordPress 外掛都使用 Composer 且依賴同一個套件的不同版本，會發生衝突。Strauss 將第三方套件的 namespace 加上前綴（如 `Psr\Http\Message` → `Recca0120\ReverseProxy\Vendor\Psr\Http\Message`），確保每個外掛使用獨立的依賴。

> **注意**：如果你要撰寫自訂中介層並使用 PSR interface，請參考[自訂中介層](#自訂中介層)章節的 namespace 說明。

## 使用方式

### 方式一：配置檔（推薦）

在 `wp-content/reverse-proxy-routes/` 目錄建立 JSON 或 PHP 配置檔：

```
wp-content/
├── reverse-proxy-routes/    # 路由配置目錄
│   ├── api.json
│   ├── legacy.json
│   └── internal.php
├── plugins/
└── uploads/
```

**JSON 格式** (`api.json`)：
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

**PHP 格式** (`internal.php`)：
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

外掛會自動載入目錄下所有 `.json` 和 `.php` 檔案。

#### 完整配置範例

**JSON 格式：**
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

**PHP 格式：**
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
                ['SetHost', 'api.example.com'],
                ['Timeout', 30],
                ['name' => 'RateLimiting', 'options' => ['limit' => 100, 'window' => 60]],
            ],
        ],
        [
            'path' => '/legacy/*',
            'target' => 'https://legacy.example.com',
        ],
    ],
];
```

#### 中介層配置格式

支援多種格式，可混合使用：

| 格式 | 說明 | 範例 |
|------|------|------|
| 字串 | 無參數中介層 | `"ProxyHeaders"` |
| 冒號格式 | `名稱:參數` (類似 Laravel) | `"SetHost:api.example.com"` |
| 冒號多參數 | `名稱:參數1,參數2` | `"RateLimiting:100,60"` |
| Pipe 字串 | 多個中介層用 `\|` 分隔 | `"ProxyHeaders\|Timeout:30"` |
| 陣列 | `[名稱, 參數...]` | `["SetHost", "api.example.com"]` |
| 物件 | 完整格式 | `{ "name": "RateLimiting", "options": {...} }` |

**範例對照：**

```php
// 以下三種寫法等效
'middlewares' => 'ProxyHeaders|SetHost:api.example.com|Timeout:30'

'middlewares' => [
    'ProxyHeaders',
    'SetHost:api.example.com',
    'Timeout:30',
]

'middlewares' => [
    ['name' => 'ProxyHeaders'],
    ['name' => 'SetHost', 'options' => 'api.example.com'],
    ['name' => 'Timeout', 'options' => 30],
]
```

**物件格式欄位：**

| 欄位 | 說明 | 範例 |
|------|------|------|
| `name` | 中介層名稱（別名或完整類別名） | `"ProxyHeaders"` |
| `args` | 位置參數陣列 | `["example.com"]` |
| `options` | 單一值或具名參數 | `30` 或 `{"limit": 100}` |

**可用的中介層別名：**
`ProxyHeaders`, `SetHost`, `RewritePath`, `RewriteBody`, `AllowMethods`, `Cors`, `IpFilter`, `RateLimiting`, `Caching`, `RequestId`, `Retry`, `CircuitBreaker`, `Timeout`, `Fallback`, `Logging`, `ErrorHandling`, `SanitizeHeaders`

#### 註冊自訂中介層別名

```php
// mu-plugins/reverse-proxy.php
add_filter('reverse_proxy_middleware_factory', function ($factory) {
    $factory->registerAlias('MyAuth', MyAuthMiddleware::class);
    $factory->registerAlias('CustomCache', MyCacheMiddleware::class);
    return $factory;
});
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

#### 自訂配置目錄

```php
// 變更配置檔目錄
add_filter('reverse_proxy_config_directory', function () {
    return WP_CONTENT_DIR . '/my-proxy-routes';
});

// 變更檔案匹配模式（預設 *.{json,php}）
add_filter('reverse_proxy_config_pattern', function () {
    return '*.routes.json';
});
```

---

### 方式二：PHP Filter Hook

建立設定檔 `wp-content/mu-plugins/reverse-proxy-config.php`：

```php
<?php
/**
 * Plugin Name: Reverse Proxy Config
 * Description: Custom reverse proxy routes configuration
 */

use Recca0120\ReverseProxy\Route;

add_filter('reverse_proxy_routes', function () {
    return [
        new Route('/api/*', 'https://api.example.com'),
    ];
});
```

---

### 混合模式

配置檔和 Filter Hook 可以同時使用。配置檔的路由會先載入，再透過 `reverse_proxy_routes` filter 合併或修改：

```php
// 在配置檔路由的基礎上新增或修改
add_filter('reverse_proxy_routes', function ($routes) {
    // 新增額外路由
    $routes[] = new Route('/custom/*', 'https://custom.example.com');

    return $routes;
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
use Recca0120\ReverseProxy\Route;

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
use Recca0120\ReverseProxy\Route;
use Recca0120\ReverseProxy\Middleware\ProxyHeaders;
use Recca0120\ReverseProxy\Middleware\SetHost;
use Recca0120\ReverseProxy\Middleware\RewritePath;

add_filter('reverse_proxy_routes', function () {
    return [
        new Route('/api/*', 'https://backend.example.com', [
            new ProxyHeaders(),
            new SetHost('custom-host.com'),
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
use Recca0120\ReverseProxy\Route;

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

### ProxyHeaders

為轉發請求新增標準代理標頭：

```php
use Recca0120\ReverseProxy\Middleware\ProxyHeaders;

new Route('/api/*', 'https://backend.example.com', [
    new ProxyHeaders(),
]);
```

新增的標頭：
- `X-Real-IP` - 客戶端 IP 位址
- `X-Forwarded-For` - 代理 IP 鏈
- `X-Forwarded-Host` - 原始主機名稱
- `X-Forwarded-Proto` - 原始協定 (http/https)
- `X-Forwarded-Port` - 原始連接埠
- `Forwarded` - RFC 7239 標準標頭

**選項設定：**

```php
// 覆寫特定值
new ProxyHeaders([
    'clientIp' => '10.0.0.1',      // 覆寫客戶端 IP
    'host' => 'example.com',       // 覆寫主機名稱
    'scheme' => 'https',           // 覆寫協定
    'port' => '443',               // 覆寫連接埠
]);

// 只傳送特定標頭（白名單）
new ProxyHeaders([
    'headers' => ['X-Real-IP', 'X-Forwarded-For'],
]);

// 排除特定標頭（黑名單）
new ProxyHeaders([
    'except' => ['Forwarded', 'X-Forwarded-Port'],
]);
```

### SetHost

設定自訂 Host 標頭：

```php
use Recca0120\ReverseProxy\Middleware\SetHost;

new Route('/api/*', 'https://127.0.0.1:8080', [
    new SetHost('api.example.com'),
]);
```

### RewritePath

重寫請求路徑，使用 Route 的萬用字元捕獲值：

```php
use Recca0120\ReverseProxy\Middleware\RewritePath;

// /api/v1/users → /v1/users
new Route('/api/v1/*', 'https://backend.example.com', [
    new RewritePath('/v1/$1'),
]);

// /legacy/users → /api/v2/users
new Route('/legacy/*', 'https://backend.example.com', [
    new RewritePath('/api/v2/$1'),
]);

// 多個萬用字元：/api/users/posts/123 → /v2/users/items/123
new Route('/api/*/posts/*', 'https://backend.example.com', [
    new RewritePath('/v2/$1/items/$2'),
]);
```

`$1`、`$2` 等對應 Route 路徑中 `*` 萬用字元的捕獲值。

### RewriteBody

使用正規表示式重寫回應內容：

```php
use Recca0120\ReverseProxy\Middleware\RewriteBody;

// 將後端 URL 替換為前端 URL
new Route('/api/*', 'https://backend.example.com', [
    new RewriteBody([
        '#https://backend\.example\.com#' => 'https://frontend.example.com',
    ]),
]);

// 多個替換規則
new Route('/api/*', 'https://backend.example.com', [
    new RewriteBody([
        '#https://api\.internal\.com#' => 'https://api.example.com',
        '#/internal/assets/#' => '/assets/',
        '#"debug":\s*true#' => '"debug": false',
    ]),
]);
```

功能：
- 使用 PHP `preg_replace()` 進行替換
- 只處理文字類型的回應（HTML, CSS, JavaScript, JSON, XML 等）
- 二進位檔案（圖片、PDF 等）不會被處理
- Key 為正規表示式 pattern，value 為替換字串

### AllowMethods

限制允許的 HTTP 方法，其他方法回傳 405 Method Not Allowed：

```php
use Recca0120\ReverseProxy\Middleware\AllowMethods;

new Route('/api/*', 'https://backend.example.com', [
    new AllowMethods(['GET', 'POST']),
]);
```

功能：
- 回傳 405 並在 `Allow` 標頭列出允許的方法
- CORS 預檢請求的 OPTIONS 總是允許
- 方法匹配不區分大小寫

**Route 方法 vs AllowMethods：**

| 面向 | Route 方法 (`POST /api/*`) | AllowMethods |
|------|---------------------------|------------------------|
| 不符合時的行為 | 跳至下一個路由 | 回傳 405 回應 |
| 使用場景 | 導向不同後端 | 限制路由允許的方法 |

### Cors

處理跨域資源共享（CORS）：

```php
use Recca0120\ReverseProxy\Middleware\Cors;

// 基本用法：允許特定來源
new Route('/api/*', 'https://backend.example.com', [
    new Cors(['https://example.com', 'https://app.example.com']),
]);

// 允許所有來源
new Route('/api/*', 'https://backend.example.com', [
    new Cors(['*']),
]);

// 完整設定
new Route('/api/*', 'https://backend.example.com', [
    new Cors(
        ['https://example.com'],           // 允許的來源
        ['GET', 'POST', 'PUT', 'DELETE'],  // 允許的方法
        ['Content-Type', 'Authorization'], // 允許的標頭
        true,                              // 允許攜帶憑證
        86400                              // 預檢快取時間（秒）
    ),
]);
```

功能：
- 自動處理 OPTIONS 預檢請求（回傳 204）
- 加入 `Access-Control-Allow-Origin` 等標頭
- 支援多個來源或萬用字元 `*`
- 可設定是否允許攜帶憑證（cookies）

### RequestId

產生或傳遞請求追蹤 ID：

```php
use Recca0120\ReverseProxy\Middleware\RequestId;

// 使用預設標頭名稱 X-Request-ID
new Route('/api/*', 'https://backend.example.com', [
    new RequestId(),
]);

// 使用自訂標頭名稱
new Route('/api/*', 'https://backend.example.com', [
    new RequestId('X-Correlation-ID'),
]);
```

功能：
- 若請求已有 ID，保留並傳遞給後端
- 若無，自動產生 UUID v4 格式的 ID
- 將 ID 加入回應標頭，方便追蹤

### IpFilter

IP 白名單/黑名單過濾：

```php
use Recca0120\ReverseProxy\Middleware\IpFilter;

// 白名單模式：只允許指定 IP
new Route('/api/*', 'https://backend.example.com', [
    IpFilter::allow(['192.168.1.100', '10.0.0.1']),
]);

// 黑名單模式：封鎖指定 IP
new Route('/api/*', 'https://backend.example.com', [
    IpFilter::deny(['192.168.1.100']),
]);

// 支援 CIDR 格式
new Route('/api/*', 'https://backend.example.com', [
    IpFilter::allow(['192.168.1.0/24', '10.0.0.0/8']),
]);
```

功能：
- 支援白名單（allow）和黑名單（deny）模式
- 支援 CIDR 表示法（如 `192.168.1.0/24`）
- 被封鎖的請求回傳 403 Forbidden

### RateLimiting

請求限流：

```php
use Recca0120\ReverseProxy\Middleware\RateLimiting;

// 每分鐘最多 60 個請求
new Route('/api/*', 'https://backend.example.com', [
    new RateLimiting(60, 60),
]);

// 每小時最多 1000 個請求
new Route('/api/*', 'https://backend.example.com', [
    new RateLimiting(1000, 3600),
]);

// 自訂限流 key（例如用 API key）
new Route('/api/*', 'https://backend.example.com', [
    new RateLimiting(100, 60, function ($request) {
        return $request->getHeaderLine('X-API-Key');
    }),
]);
```

功能：
- 預設以 IP 為限流單位
- 回應包含 `X-RateLimit-*` 標頭
- 超過限制回傳 429 Too Many Requests 及 `Retry-After` 標頭

### Caching

回應快取：

```php
use Recca0120\ReverseProxy\Middleware\Caching;

// 快取 5 分鐘
new Route('/api/*', 'https://backend.example.com', [
    new Caching(300),
]);

// 快取 1 小時
new Route('/api/*', 'https://backend.example.com', [
    new Caching(3600),
]);
```

功能：
- 只快取 GET/HEAD 請求
- 只快取 200 OK 回應
- 尊重 `Cache-Control: no-cache/no-store/private`
- 回應包含 `X-Cache: HIT/MISS` 標頭
- 使用 WordPress transients 儲存

### Retry

失敗自動重試：

```php
use Recca0120\ReverseProxy\Middleware\Retry;

// 最多重試 3 次
new Route('/api/*', 'https://backend.example.com', [
    new Retry(3),
]);

// 自訂可重試的方法和狀態碼
new Route('/api/*', 'https://backend.example.com', [
    new Retry(
        3,                            // 最大重試次數
        ['GET', 'PUT', 'DELETE'],     // 可重試的方法
        [502, 503, 504]               // 可重試的狀態碼
    ),
]);
```

功能：
- 預設只重試 GET/HEAD/OPTIONS 請求
- 遇到 502/503/504 或網路錯誤時自動重試
- 不重試 4xx 錯誤（客戶端錯誤）

### CircuitBreaker

熔斷器模式：

```php
use Recca0120\ReverseProxy\Middleware\CircuitBreaker;

new Route('/api/*', 'https://backend.example.com', [
    new CircuitBreaker(
        'my-service',  // 服務名稱（識別不同的 circuit）
        5,             // 失敗閾值
        60             // 重置超時（秒）
    ),
]);
```

功能：
- 連續失敗達到閾值時開啟熔斷器
- 熔斷器開啟時直接返回 503，不呼叫後端
- 超時後自動嘗試恢復
- 成功請求會重置失敗計數

### Timeout

請求超時控制：

```php
use Recca0120\ReverseProxy\Middleware\Timeout;

// 30 秒超時
new Route('/api/*', 'https://backend.example.com', [
    new Timeout(30),
]);

// 5 秒超時（適合快速失敗）
new Route('/api/*', 'https://backend.example.com', [
    new Timeout(5),
]);
```

功能：
- 設定請求超時時間
- 超時時返回 504 Gateway Timeout
- 透過 `X-Timeout` header 傳遞超時設定

### Fallback

當後端回傳指定狀態碼時，回退給 WordPress 處理：

```php
use Recca0120\ReverseProxy\Middleware\Fallback;

// 404 時回退給 WordPress（預設）
new Route('/api/*', 'https://backend.example.com', [
    new Fallback(),
]);

// 多個狀態碼
new Route('/api/*', 'https://backend.example.com', [
    new Fallback([404, 410]),
]);
```

功能：
- 預設只在 404 時回退
- 回退時 WordPress 會繼續處理請求（例如顯示 WordPress 404 頁面）
- 適用於讓 WordPress 處理找不到的資源

### ErrorHandling（預設啟用）

捕獲 HTTP 客戶端異常，回傳 502 Bad Gateway：

```php
use Recca0120\ReverseProxy\Middleware\ErrorHandling;

new Route('/api/*', 'https://backend.example.com', [
    new ErrorHandling(),
]);
```

功能：
- 捕獲 `ClientExceptionInterface`（連線逾時、拒絕連線等）
- 回傳 502 狀態碼與 JSON 錯誤訊息
- 其他異常會繼續向上拋出

### Logging（預設啟用）

記錄代理請求與回應：

```php
use Recca0120\ReverseProxy\Middleware\Logging;
use Recca0120\ReverseProxy\WordPress\Logger;

new Route('/api/*', 'https://backend.example.com', [
    new Logging(new Logger()),
]);
```

記錄內容：
- 請求：HTTP 方法、目標 URL
- 回應：狀態碼
- 錯誤：異常訊息（然後重新拋出）

> **注意**：`ErrorHandling` 和 `Logging` 預設會自動加入所有路由，不需手動設定。

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

> **Namespace 注意事項**
>
> PSR interface 的 namespace 在正式版和開發版不同：
>
> | 版本 | Namespace |
> |------|-----------|
> | 正式版 | `Recca0120\ReverseProxy\Vendor\Psr\Http\Message\*` |
> | 開發版 | `Psr\Http\Message\*` |
>
> 以下範例以**正式版**為主。如果是開發版，請將 `Recca0120\ReverseProxy\Vendor\Psr\*` 改為 `Psr\*`。

```php
use Recca0120\ReverseProxy\Contracts\MiddlewareInterface;
use Recca0120\ReverseProxy\Vendor\Psr\Http\Message\ServerRequestInterface;
use Recca0120\ReverseProxy\Vendor\Psr\Http\Message\ResponseInterface;

class AddAuthHeader implements MiddlewareInterface
{
    private $token;

    public function __construct(string $token)
    {
        $this->token = $token;
    }

    public function process(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        return $next($request->withHeader('Authorization', 'Bearer ' . $this->token));
    }
}

// 使用方式
$route = (new Route('/api/*', 'https://backend.example.com'))
    ->middleware(new AddAuthHeader('my-secret-token'));
```

**替代方案：使用閉包（無需 import PSR interface）**

如果不想處理 namespace 差異，可以使用閉包：

```php
$route = (new Route('/api/*', 'https://backend.example.com'))
    ->middleware(function ($request, $next) {
        return $next($request->withHeader('Authorization', 'Bearer my-secret-token'));
    });
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
    ->middleware(new ProxyHeaders())
    ->middleware(new SetHost('api.example.com'))
    ->middleware($authMiddleware)
    ->middleware($loggingMiddleware);
```

或透過建構子：
```php
$route = new Route('/api/*', 'https://backend.example.com', [
    new ProxyHeaders(),
    new SetHost('api.example.com'),
    $authMiddleware,
    $loggingMiddleware,
]);
```

中介層以洋蔥式順序執行：
```
Request → [MW1 → [MW2 → [MW3 → Proxy] ← MW3] ← MW2] ← MW1 → Response
```

### 中介層優先權

中介層可透過 `$priority` 屬性控制執行順序（數字越小越先執行，即越外層）：

```php
use Recca0120\ReverseProxy\Contracts\MiddlewareInterface;
// 正式版使用 Recca0120\ReverseProxy\Vendor\Psr\*，開發版使用 Psr\*
use Recca0120\ReverseProxy\Vendor\Psr\Http\Message\ServerRequestInterface;
use Recca0120\ReverseProxy\Vendor\Psr\Http\Message\ResponseInterface;

class AuthMiddleware implements MiddlewareInterface
{
    public $priority = -50;  // 比預設 (0) 更早執行

    public function process(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        return $next($request->withHeader('Authorization', 'Bearer token'));
    }
}
```

內建優先權：
- `ErrorHandling`: -100（最外層，捕獲所有錯誤）
- `Logging`: -90（記錄所有請求）
- 自訂中介層預設: 0

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
use Recca0120\ReverseProxy\Route;
use Recca0120\ReverseProxy\Middleware\ProxyHeaders;
use Recca0120\ReverseProxy\Middleware\SetHost;

add_filter('reverse_proxy_routes', function () {
    return [
        new Route('/api/v1/*', 'https://127.0.0.1:8080', [
            new ProxyHeaders(),
            new SetHost('api.example.com'),
        ]),
    ];
});
```

## 可用 Hooks

### Filters

| Hook | 參數 | 說明 |
|------|------|------|
| `reverse_proxy_action_hook` | `$hook` | 設定觸發的 action hook（預設 `plugins_loaded`） |
| `reverse_proxy_routes` | `$routes` | 設定代理路由 |
| `reverse_proxy_config_loader` | `$loader` | 覆寫配置載入器 |
| `reverse_proxy_middleware_factory` | `$factory` | 自訂中介層工廠（可註冊自訂別名） |
| `reverse_proxy_config_directory` | `$directory` | 配置檔目錄（預設 `WP_CONTENT_DIR/reverse-proxy-routes`） |
| `reverse_proxy_config_pattern` | `$pattern` | 配置檔匹配模式（預設 `*.{json,php}`） |
| `reverse_proxy_config_cache` | `$cache` | PSR-16 快取實例（用於快取配置） |
| `reverse_proxy_default_middlewares` | `$middlewares` | 自訂預設中介層 |
| `reverse_proxy_psr17_factory` | `$factory` | 覆寫 PSR-17 HTTP 工廠 |
| `reverse_proxy_http_client` | `$client` | 覆寫 PSR-18 HTTP 客戶端 |
| `reverse_proxy_request` | `$request` | 覆寫整個請求物件（用於測試） |
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

## 運作原理

1. 外掛掛載到 WordPress `plugins_loaded` action
2. 檢查請求路徑是否符合任何已設定的路由
3. 如果符合：
   - 轉發請求到目標伺服器
   - 回傳回應給客戶端
   - 結束（停止 WordPress 處理）
4. 如果不符合：
   - WordPress 繼續正常處理請求

```
Request → plugins_loaded → 符合路由？
                               ↓
                     是 ──→ 代理 → 回應 → 結束
                               ↓
                     否 ──→ WordPress 正常處理
```

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

use Recca0120\ReverseProxy\Route;

require_once WP_CONTENT_DIR.'/plugins/reverse-proxy/reverse-proxy.php';

// 註冊路由
add_filter('reverse_proxy_routes', function () {
    return [
        new Route('/api/*', 'https://api.example.com'),
    ];
});

// 直接執行（跳過後續 WordPress 載入）
reverse_proxy_handle();
```

這是最快的執行方式，在 mu-plugin 階段直接處理請求。

## 開發

### 設定

```bash
# 安裝依賴
composer install

# 設定 WordPress 測試環境（使用 SQLite，預設）
./bin/install-wp-tests.sh

# 或使用 MySQL
./bin/install-wp-tests.sh mysql

# 執行測試
composer test
```

### 執行測試

```bash
# 所有測試（使用 SQLite）
DB_ENGINE=sqlite ./vendor/bin/phpunit

# 或直接使用 composer
composer test

# 特定測試
./vendor/bin/phpunit --filter=test_it_proxies_request

# 含覆蓋率
./vendor/bin/phpunit --coverage-text
```

### 支援的 PHP 版本

- PHP 7.2 ~ 8.4（CI 測試涵蓋）

## 授權

MIT License - 詳見 [LICENSE](LICENSE)。

## 作者

Recca Tsai - [recca0120@gmail.com](mailto:recca0120@gmail.com)
