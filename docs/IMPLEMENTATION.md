# WP Reverse Proxy - Implementation Plan

## Project Overview

WordPress plugin that proxies specific URL paths to external backend servers.

### Core Concepts

- **Route Matching**: Match request paths against configured rules (e.g., `/api/*`)
- **Request Proxying**: Forward matched requests to target servers
- **Response Handling**: Return backend response to client
- **WordPress Integration**: Hook into `parse_request` to intercept requests

### Configuration via Filter

```php
add_filter('reverse_proxy_rules', function ($rules) {
    $rules[] = [
        'source' => '/api/*',
        'target' => 'https://backend.example.com',
    ];
    return $rules;
});
```

---

## Implementation Progress

### Phase 1: Core Infrastructure [COMPLETED]

- [x] Project setup (composer, phpunit, GitHub Actions)
- [x] Basic integration test with WordPress flow
- [x] `ReverseProxy` class with PSR-18 HTTP Client
- [x] Route matching with wildcard support (`*`)
- [x] `parse_request` hook integration
- [x] Mock HTTP Client for testing (`php-http/mock-client`)

### Phase 2: Request Handling [COMPLETED]

- [x] POST request with body forwarding
- [x] Request headers forwarding (Authorization, Content-Type, etc.)
- [x] Query string preservation
- [ ] Request body for different content types (JSON, form-data)

### Phase 3: Response Handling [COMPLETED]

- [x] Response headers forwarding
- [x] Response status code handling (404, 500, etc.)
- [x] Error handling (connection refused → 502 Bad Gateway)
- [ ] Streaming large responses

### Phase 4: Advanced Features [TODO]

- [ ] Multiple rules with priority/order
- [ ] Path rewriting (e.g., `/api/v1/*` -> `/v1/*`)
- [ ] Host header configuration
- [ ] Timeout configuration
- [ ] Retry logic

### Phase 5: Production Ready [TODO]

- [ ] Real HTTP client (Guzzle or wp_remote_request)
- [ ] Logging integration
- [ ] Performance optimization
- [ ] Documentation

---

## Test Cases

### Integration Tests (WordPress Flow)

| Test | Status | Description |
|------|--------|-------------|
| `test_it_proxies_request_matching_rule_to_target_server` | ✅ | Matching path triggers proxy |
| `test_it_does_not_proxy_request_not_matching_any_rule` | ✅ | Non-matching path skips proxy |
| `test_wordpress_continues_normally_for_non_matching_requests` | ✅ | WordPress handles normal requests |
| `test_it_forwards_post_request_with_body` | ✅ | POST body forwarding |
| `test_it_forwards_request_headers` | ✅ | Headers forwarding |
| `test_it_preserves_query_string` | ✅ | Query string handling |
| `test_it_forwards_backend_error_status_code` | ✅ | 404 error forwarding |
| `test_it_forwards_backend_500_error` | ✅ | 500 error forwarding |
| `test_it_handles_connection_error` | ✅ | Connection error → 502 |
| `test_it_forwards_response_headers` | ✅ | Response headers forwarding |

### Planned Tests
| `test_it_matches_rules_in_order` | 4 | Rule priority |
| `test_it_rewrites_path` | 4 | Path rewriting |

---

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                     WordPress Request                        │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                   parse_request hook                         │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                     ReverseProxy                             │
│  ┌─────────────────────────────────────────────────────────┐│
│  │ 1. Get rules from filter                                ││
│  │ 2. Match request path against rules                     ││
│  │ 3. If match: proxy request, return response, exit       ││
│  │ 4. If no match: return false, continue WordPress        ││
│  └─────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────┘
                              │
              ┌───────────────┴───────────────┐
              ▼                               ▼
┌──────────────────────┐         ┌──────────────────────┐
│   Match: Proxy       │         │  No Match: WordPress │
│   - Build target URL │         │  - Normal routing    │
│   - Forward request  │         │  - Theme/template    │
│   - Return response  │         │  - Plugins           │
│   - Exit             │         │                      │
└──────────────────────┘         └──────────────────────┘
```

---

## File Structure

```
reverse-proxy/
├── includes/
│   └── ReverseProxy.php        # Main proxy class
├── tests/
│   ├── Integration/
│   │   └── ReverseProxyTest.php
│   ├── bootstrap.php
│   └── wp-config.php
├── docs/
│   └── IMPLEMENTATION.md       # This file
├── bin/
│   └── install-wp-tests.sh
├── .github/
│   └── workflows/
│       └── tests.yml
├── composer.json
├── phpunit.xml.dist
└── reverse-proxy.php           # Plugin entry point
```

---

## Dependencies

### Production
- `psr/http-client` - PSR-18 HTTP Client interface
- `psr/http-factory` - PSR-17 HTTP Factories
- `nyholm/psr7` - PSR-7 implementation

### Development
- `php-http/mock-client` - Mock HTTP client for testing
- `phpunit/phpunit` - Testing framework
- `wp-phpunit/wp-phpunit` - WordPress test utilities
