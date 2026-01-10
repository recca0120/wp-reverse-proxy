# Development Guide

[← Back to main documentation](../../README.en.md)

This document explains how to set up the development environment, run tests, and understand the difference between production and development versions.

## Table of Contents

- [Production vs Development](#production-vs-development)
- [Development Setup](#development-setup)
- [Running Tests](#running-tests)
- [Supported PHP Versions](#supported-php-versions)

---

## Production vs Development

This plugin uses [Strauss](https://github.com/BrianHenryIE/strauss) to prefix third-party dependency namespaces, preventing conflicts with other WordPress plugins.

| Version | Source | Namespace | Use Case |
|---------|--------|-----------|----------|
| **Production** | GitHub Releases ZIP | `Recca0120\ReverseProxy\Vendor\*` | General use |
| **Development** | Git clone + composer | Original namespace (e.g., `Psr\*`) | Development/Contributing |

### Why Strauss?

When two WordPress plugins use Composer with the same dependency but different versions, conflicts occur. Strauss prefixes third-party package namespaces (e.g., `Psr\Http\Message` → `Recca0120\ReverseProxy\Vendor\Psr\Http\Message`), ensuring each plugin uses isolated dependencies.

> **Note**: If you need to write custom middleware using PSR interfaces, see the namespace notes in [Middleware documentation](middlewares.md#custom-middleware).

---

## Development Setup

### Install Dependencies

```bash
# Clone the project
git clone https://github.com/recca0120/wp-reverse-proxy.git
cd wp-reverse-proxy

# Install Composer dependencies
composer install
```

### Setup WordPress Test Environment

The plugin supports both SQLite (default) and MySQL for testing:

```bash
# Use SQLite (default, no additional setup needed)
./bin/install-wp-tests.sh

# Or use MySQL
./bin/install-wp-tests.sh mysql
```

---

## Running Tests

### Basic Tests

```bash
# All tests (with SQLite)
DB_ENGINE=sqlite ./vendor/bin/phpunit

# Or use composer directly
composer test
```

### Specific Tests

```bash
# Run specific test
./vendor/bin/phpunit --filter=test_it_proxies_request

# Run specific test class
./vendor/bin/phpunit tests/Unit/Routing/RouteTest.php
```

### Test Coverage

```bash
# Text format coverage report
./vendor/bin/phpunit --coverage-text

# HTML format coverage report
./vendor/bin/phpunit --coverage-html coverage/
```

---

## Supported PHP Versions

- PHP 7.2 ~ 8.4 (covered by CI)

All versions are automatically tested in GitHub Actions CI.

---

## How It Works

1. Plugin hooks into WordPress `plugins_loaded` action
2. Checks if request path matches any configured route
3. If matched:
   - Forwards request to target server
   - Returns response to client
   - Exits (stops WordPress processing)
4. If not matched:
   - WordPress continues normal request handling

```
Request → plugins_loaded → Match Route?
                               ↓
                     Yes ──→ Proxy → Response → Exit
                               ↓
                     No  ──→ WordPress handles normally
```
