# 開發指南

[← 返回主文件](../../README.md)

本文件說明如何設定開發環境、執行測試，以及正式版與開發版的差異。

## 目錄

- [正式版 vs 開發版](#正式版-vs-開發版)
- [開發環境設定](#開發環境設定)
- [執行測試](#執行測試)
- [支援的 PHP 版本](#支援的-php-版本)

---

## 正式版 vs 開發版

本外掛使用 [Strauss](https://github.com/BrianHenryIE/strauss) 來處理第三方依賴的 namespace，避免與其他 WordPress 外掛的依賴衝突。

| 版本 | 來源 | Namespace | 適用情境 |
|------|------|-----------|----------|
| **正式版** | GitHub Releases ZIP | `Recca0120\ReverseProxy\Vendor\*` | 一般使用 |
| **開發版** | Git clone + composer | 原始 namespace（如 `Psr\*`） | 開發/貢獻 |

### 為什麼需要 Strauss？

如果兩個 WordPress 外掛都使用 Composer 且依賴同一個套件的不同版本，會發生衝突。Strauss 將第三方套件的 namespace 加上前綴（如 `Psr\Http\Message` → `Recca0120\ReverseProxy\Vendor\Psr\Http\Message`），確保每個外掛使用獨立的依賴。

> **注意**：如果你要撰寫自訂中介層並使用 PSR interface，請參考 [中介層文件](middlewares.md#自訂中介層) 的 namespace 說明。

---

## 開發環境設定

### 安裝依賴

```bash
# Clone 專案
git clone https://github.com/recca0120/wp-reverse-proxy.git
cd wp-reverse-proxy

# 安裝 Composer 依賴
composer install
```

### 設定 WordPress 測試環境

外掛支援 SQLite（預設）和 MySQL 兩種測試資料庫：

```bash
# 使用 SQLite（預設，無需額外設定）
./bin/install-wp-tests.sh

# 或使用 MySQL
./bin/install-wp-tests.sh mysql
```

---

## 執行測試

### 基本測試

```bash
# 所有測試（使用 SQLite）
DB_ENGINE=sqlite ./vendor/bin/phpunit

# 或直接使用 composer
composer test
```

### 特定測試

```bash
# 執行特定測試
./vendor/bin/phpunit --filter=test_it_proxies_request

# 執行特定測試類別
./vendor/bin/phpunit tests/Unit/Routing/RouteTest.php
```

### 測試覆蓋率

```bash
# 文字格式覆蓋率報告
./vendor/bin/phpunit --coverage-text

# HTML 格式覆蓋率報告
./vendor/bin/phpunit --coverage-html coverage/
```

---

## 支援的 PHP 版本

- PHP 7.2 ~ 8.4（CI 測試涵蓋）

所有版本都在 GitHub Actions CI 中自動測試。

---

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
