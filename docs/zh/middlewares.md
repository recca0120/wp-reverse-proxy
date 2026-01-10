# 中介層參考

[← 返回主文件](../../README.md)

本文件詳細說明所有內建中介層的用法與設定。

## 目錄

- [ProxyHeaders](#proxyheaders)
- [SetHost](#sethost)
- [RewritePath](#rewritepath)
- [RewriteBody](#rewritebody)
- [AllowMethods](#allowmethods)
- [Cors](#cors)
- [RequestId](#requestid)
- [IpFilter](#ipfilter)
- [RateLimiting](#ratelimiting)
- [Caching](#caching)
- [Retry](#retry)
- [CircuitBreaker](#circuitbreaker)
- [Timeout](#timeout)
- [Fallback](#fallback)
- [ErrorHandling](#errorhandling)
- [Logging](#logging)

---

## ProxyHeaders

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

---

## SetHost

設定自訂 Host 標頭：

```php
use Recca0120\ReverseProxy\Middleware\SetHost;

new Route('/api/*', 'https://127.0.0.1:8080', [
    new SetHost('api.example.com'),
]);
```

---

## RewritePath

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

---

## RewriteBody

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

---

## AllowMethods

限制允許的 HTTP 方法，其他方法回傳 405 Method Not Allowed：

```php
use Recca0120\ReverseProxy\Middleware\AllowMethods;

new Route('/api/*', 'https://backend.example.com', [
    new AllowMethods('GET', 'POST'),
]);
```

**配置檔格式：**
```json
"middlewares": ["AllowMethods:GET,POST,PUT"]
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

---

## Cors

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

---

## RequestId

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

---

## IpFilter

IP 白名單/黑名單過濾：

```php
use Recca0120\ReverseProxy\Middleware\IpFilter;

// 白名單模式：只允許指定 IP
new Route('/api/*', 'https://backend.example.com', [
    IpFilter::allow('192.168.1.100', '10.0.0.1'),
]);

// 黑名單模式：封鎖指定 IP
new Route('/api/*', 'https://backend.example.com', [
    IpFilter::deny('192.168.1.100'),
]);

// 支援 CIDR 格式
new Route('/api/*', 'https://backend.example.com', [
    IpFilter::allow('192.168.1.0/24', '10.0.0.0/8'),
]);
```

**配置檔格式：**
```json
"middlewares": [
  "IpFilter:allow,192.168.1.100,10.0.0.1",
  "IpFilter:deny,192.168.1.100",
  "IpFilter:192.168.1.0/24"
]
```

> 若省略模式（allow/deny），預設為 `allow`。

功能：
- 支援白名單（allow）和黑名單（deny）模式
- 支援 CIDR 表示法（如 `192.168.1.0/24`）
- 被封鎖的請求回傳 403 Forbidden

---

## RateLimiting

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

---

## Caching

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

---

## Retry

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

---

## CircuitBreaker

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

---

## Timeout

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

---

## Fallback

當後端回傳指定狀態碼時，回退給 WordPress 處理：

```php
use Recca0120\ReverseProxy\Middleware\Fallback;

// 404 時回退給 WordPress（預設）
new Route('/api/*', 'https://backend.example.com', [
    new Fallback(),
]);

// 多個狀態碼
new Route('/api/*', 'https://backend.example.com', [
    new Fallback(404, 410, 500),
]);
```

**配置檔格式：**
```json
"middlewares": ["Fallback:404,500,502"]
```

功能：
- 預設只在 404 時回退
- 回退時 WordPress 會繼續處理請求（例如顯示 WordPress 404 頁面）
- 適用於讓 WordPress 處理找不到的資源

---

## ErrorHandling

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

> **注意**：此中介層預設自動加入所有路由。

---

## Logging

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

> **注意**：此中介層預設自動加入所有路由。

---

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
