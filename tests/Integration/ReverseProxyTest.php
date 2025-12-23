<?php

namespace ReverseProxy\Tests\Integration;

use Http\Client\Exception\NetworkException;
use Http\Mock\Client as MockClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Request;
use Nyholm\Psr7\Response;
use ReverseProxy\ReverseProxy;
use WP_UnitTestCase;

class ReverseProxyTest extends WP_UnitTestCase
{
    private MockClient $mockClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockClient = new MockClient();

        // 注入 Mock Client 到插件
        add_filter('reverse_proxy_http_client', function () {
            return $this->mockClient;
        });

        // 測試時不要 exit
        add_filter('reverse_proxy_should_exit', '__return_false');
    }

    protected function tearDown(): void
    {
        remove_all_filters('reverse_proxy_rules');
        remove_all_filters('reverse_proxy_http_client');
        remove_all_filters('reverse_proxy_should_exit');
        parent::tearDown();
    }

    public function test_it_proxies_request_matching_rule_to_target_server()
    {
        // Given: 註冊代理規則
        add_filter('reverse_proxy_rules', function ($rules) {
            $rules[] = [
                'source' => '/api/*',
                'target' => 'https://backend.example.com',
            ];

            return $rules;
        });

        // And: Mock 後端回應
        $this->mockClient->addResponse(
            new Response(200, ['Content-Type' => 'application/json'], '{"message":"hello"}')
        );

        // When: 透過 WordPress 請求 /api/users
        ob_start();
        $this->go_to('/api/users');
        $output = ob_get_clean();

        // Then: 驗證發送的請求是正確的
        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertNotFalse($lastRequest, 'Should have made a proxy request');
        $this->assertEquals('GET', $lastRequest->getMethod());
        $this->assertEquals('https://backend.example.com/api/users', (string) $lastRequest->getUri());

        // And: 驗證回應內容
        $this->assertEquals('{"message":"hello"}', $output);
    }

    public function test_it_does_not_proxy_request_not_matching_any_rule()
    {
        // Given: 註冊代理規則
        add_filter('reverse_proxy_rules', function ($rules) {
            $rules[] = [
                'source' => '/api/*',
                'target' => 'https://backend.example.com',
            ];

            return $rules;
        });

        // When: 請求不匹配的路徑
        $this->go_to('/about');

        // Then: 不應該發送代理請求
        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertFalse($lastRequest, 'Should not have made a proxy request');
    }

    public function test_wordpress_continues_normally_for_non_matching_requests()
    {
        // Given: 建立一篇文章
        $post_id = $this->factory->post->create([
            'post_title' => 'Hello World',
            'post_status' => 'publish',
        ]);

        // And: 註冊代理規則
        add_filter('reverse_proxy_rules', function ($rules) {
            $rules[] = [
                'source' => '/api/*',
                'target' => 'https://backend.example.com',
            ];

            return $rules;
        });

        // When: 請求這篇文章
        $this->go_to(get_permalink($post_id));

        // Then: WordPress 應該正常處理，找到這篇文章
        $this->assertTrue(is_single());
        $this->assertEquals($post_id, get_the_ID());

        // And: 不應該發送代理請求
        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertFalse($lastRequest);
    }

    public function test_it_forwards_post_request_with_body()
    {
        // Given: 註冊代理規則
        add_filter('reverse_proxy_rules', function ($rules) {
            $rules[] = [
                'source' => '/api/*',
                'target' => 'https://backend.example.com',
            ];

            return $rules;
        });

        // And: Mock 後端回應
        $this->mockClient->addResponse(
            new Response(201, ['Content-Type' => 'application/json'], '{"id":1}')
        );

        // And: 設置 POST 請求
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $requestBody = '{"name":"John","email":"john@example.com"}';

        // 模擬 php://input
        add_filter('reverse_proxy_request_body', function () use ($requestBody) {
            return $requestBody;
        });

        // When: 透過 WordPress 請求 /api/users
        ob_start();
        $this->go_to('/api/users');
        $output = ob_get_clean();

        // Then: 驗證發送的請求是 POST
        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertNotFalse($lastRequest, 'Should have made a proxy request');
        $this->assertEquals('POST', $lastRequest->getMethod());
        $this->assertEquals('https://backend.example.com/api/users', (string) $lastRequest->getUri());

        // And: 驗證請求 body 被轉發
        $this->assertEquals($requestBody, (string) $lastRequest->getBody());

        // And: 驗證回應
        $this->assertEquals('{"id":1}', $output);
    }

    public function test_it_forwards_request_headers()
    {
        // Given: 註冊代理規則
        add_filter('reverse_proxy_rules', function ($rules) {
            $rules[] = [
                'source' => '/api/*',
                'target' => 'https://backend.example.com',
            ];

            return $rules;
        });

        // And: Mock 後端回應
        $this->mockClient->addResponse(
            new Response(200, ['Content-Type' => 'application/json'], '{"authenticated":true}')
        );

        // And: 設置請求 headers
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer token123';
        $_SERVER['HTTP_X_CUSTOM_HEADER'] = 'custom-value';
        $_SERVER['CONTENT_TYPE'] = 'application/json';

        // When: 透過 WordPress 請求 /api/users
        ob_start();
        $this->go_to('/api/users');
        ob_get_clean();

        // Then: 驗證 headers 被轉發
        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertNotFalse($lastRequest);
        $this->assertEquals('Bearer token123', $lastRequest->getHeaderLine('Authorization'));
        $this->assertEquals('custom-value', $lastRequest->getHeaderLine('X-Custom-Header'));
        $this->assertEquals('application/json', $lastRequest->getHeaderLine('Content-Type'));
    }

    public function test_it_preserves_query_string()
    {
        // Given: 註冊代理規則
        add_filter('reverse_proxy_rules', function ($rules) {
            $rules[] = [
                'source' => '/api/*',
                'target' => 'https://backend.example.com',
            ];

            return $rules;
        });

        // And: Mock 後端回應
        $this->mockClient->addResponse(
            new Response(200, ['Content-Type' => 'application/json'], '{"users":[]}')
        );

        // When: 請求帶有 query string
        ob_start();
        $this->go_to('/api/users?page=2&limit=10&sort=name');
        ob_get_clean();

        // Then: 驗證 query string 被保留
        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertNotFalse($lastRequest);
        $this->assertEquals(
            'https://backend.example.com/api/users?page=2&limit=10&sort=name',
            (string) $lastRequest->getUri()
        );
    }

    public function test_it_forwards_backend_error_status_code()
    {
        // Given: 註冊代理規則
        add_filter('reverse_proxy_rules', function ($rules) {
            $rules[] = [
                'source' => '/api/*',
                'target' => 'https://backend.example.com',
            ];

            return $rules;
        });

        // And: Mock 後端回應 404 錯誤
        $this->mockClient->addResponse(
            new Response(404, ['Content-Type' => 'application/json'], '{"error":"Not Found"}')
        );

        // And: 捕獲 response 資訊
        $capturedResponse = null;
        add_filter('reverse_proxy_response', function ($response) use (&$capturedResponse) {
            $capturedResponse = $response;

            return $response;
        });

        // When: 請求 /api/users/999
        ob_start();
        $this->go_to('/api/users/999');
        $output = ob_get_clean();

        // Then: 驗證狀態碼和回應
        $this->assertNotNull($capturedResponse);
        $this->assertEquals(404, $capturedResponse->getStatusCode());
        $this->assertEquals('{"error":"Not Found"}', $output);
    }

    public function test_it_forwards_backend_500_error()
    {
        // Given: 註冊代理規則
        add_filter('reverse_proxy_rules', function ($rules) {
            $rules[] = [
                'source' => '/api/*',
                'target' => 'https://backend.example.com',
            ];

            return $rules;
        });

        // And: Mock 後端回應 500 錯誤
        $this->mockClient->addResponse(
            new Response(500, ['Content-Type' => 'application/json'], '{"error":"Internal Server Error"}')
        );

        // And: 捕獲 response 資訊
        $capturedResponse = null;
        add_filter('reverse_proxy_response', function ($response) use (&$capturedResponse) {
            $capturedResponse = $response;

            return $response;
        });

        // When: 請求 /api/crash
        ob_start();
        $this->go_to('/api/crash');
        $output = ob_get_clean();

        // Then: 驗證狀態碼和回應
        $this->assertNotNull($capturedResponse);
        $this->assertEquals(500, $capturedResponse->getStatusCode());
        $this->assertEquals('{"error":"Internal Server Error"}', $output);
    }

    public function test_it_handles_connection_error()
    {
        // Given: 註冊代理規則
        add_filter('reverse_proxy_rules', function ($rules) {
            $rules[] = [
                'source' => '/api/*',
                'target' => 'https://backend.example.com',
            ];

            return $rules;
        });

        // And: Mock 連線錯誤
        $this->mockClient->addException(
            new NetworkException('Connection refused', new Request('GET', 'https://backend.example.com/api/users'))
        );

        // And: 捕獲錯誤
        $capturedError = null;
        add_action('reverse_proxy_error', function ($error) use (&$capturedError) {
            $capturedError = $error;
        });

        // When: 請求 /api/users
        ob_start();
        $this->go_to('/api/users');
        $output = ob_get_clean();

        // Then: 驗證錯誤被捕獲
        $this->assertNotNull($capturedError);
        $this->assertInstanceOf(NetworkException::class, $capturedError);

        // And: 應該返回 502 Bad Gateway
        $this->assertStringContainsString('502', $output);
    }

    public function test_it_forwards_response_headers()
    {
        // Given: 註冊代理規則
        add_filter('reverse_proxy_rules', function ($rules) {
            $rules[] = [
                'source' => '/api/*',
                'target' => 'https://backend.example.com',
            ];

            return $rules;
        });

        // And: Mock 後端回應帶有自訂 headers
        $this->mockClient->addResponse(
            new Response(200, [
                'Content-Type' => 'application/json',
                'X-Custom-Header' => 'custom-value',
                'X-Request-Id' => 'abc123',
                'Cache-Control' => 'no-cache',
            ], '{"data":"test"}')
        );

        // And: 捕獲 response 資訊
        $capturedResponse = null;
        add_filter('reverse_proxy_response', function ($response) use (&$capturedResponse) {
            $capturedResponse = $response;

            return $response;
        });

        // When: 請求 /api/data
        ob_start();
        $this->go_to('/api/data');
        $output = ob_get_clean();

        // Then: 驗證 response headers 存在
        $this->assertNotNull($capturedResponse);
        $this->assertEquals('application/json', $capturedResponse->getHeaderLine('Content-Type'));
        $this->assertEquals('custom-value', $capturedResponse->getHeaderLine('X-Custom-Header'));
        $this->assertEquals('abc123', $capturedResponse->getHeaderLine('X-Request-Id'));
        $this->assertEquals('no-cache', $capturedResponse->getHeaderLine('Cache-Control'));

        // And: 驗證回應內容
        $this->assertEquals('{"data":"test"}', $output);
    }

    public function test_it_matches_first_matching_rule()
    {
        // Given: 註冊多個代理規則（順序很重要）
        add_filter('reverse_proxy_rules', function ($rules) {
            // 更具體的規則應該放前面
            $rules[] = [
                'source' => '/api/v2/*',
                'target' => 'https://api-v2.example.com',
            ];
            $rules[] = [
                'source' => '/api/*',
                'target' => 'https://api.example.com',
            ];

            return $rules;
        });

        // And: Mock 後端回應
        $this->mockClient->addResponse(
            new Response(200, [], '{"version":"v2"}')
        );

        // When: 請求 /api/v2/users（應該匹配第一個規則）
        ob_start();
        $this->go_to('/api/v2/users');
        ob_get_clean();

        // Then: 應該代理到 api-v2.example.com
        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertNotFalse($lastRequest);
        $this->assertEquals(
            'https://api-v2.example.com/api/v2/users',
            (string) $lastRequest->getUri()
        );
    }

    public function test_it_falls_through_to_next_rule()
    {
        // Given: 註冊多個代理規則
        add_filter('reverse_proxy_rules', function ($rules) {
            $rules[] = [
                'source' => '/api/v2/*',
                'target' => 'https://api-v2.example.com',
            ];
            $rules[] = [
                'source' => '/api/*',
                'target' => 'https://api.example.com',
            ];

            return $rules;
        });

        // And: Mock 後端回應
        $this->mockClient->addResponse(
            new Response(200, [], '{"version":"v1"}')
        );

        // When: 請求 /api/users（不匹配 v2，應該匹配第二個規則）
        ob_start();
        $this->go_to('/api/users');
        ob_get_clean();

        // Then: 應該代理到 api.example.com
        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertNotFalse($lastRequest);
        $this->assertEquals(
            'https://api.example.com/api/users',
            (string) $lastRequest->getUri()
        );
    }

    public function test_it_rewrites_path()
    {
        // Given: 註冊帶有 path rewrite 的規則
        add_filter('reverse_proxy_rules', function ($rules) {
            $rules[] = [
                'source' => '/api/v1/*',
                'target' => 'https://backend.example.com',
                'rewrite' => '/v1/$1',  // $1 = wildcard 匹配的部分
            ];

            return $rules;
        });

        // And: Mock 後端回應
        $this->mockClient->addResponse(
            new Response(200, [], '{"rewritten":true}')
        );

        // When: 請求 /api/v1/users/123
        ob_start();
        $this->go_to('/api/v1/users/123');
        ob_get_clean();

        // Then: 應該代理到 /v1/users/123（移除 /api 前綴）
        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertNotFalse($lastRequest);
        $this->assertEquals(
            'https://backend.example.com/v1/users/123',
            (string) $lastRequest->getUri()
        );
    }

    public function test_it_rewrites_path_with_static_replacement()
    {
        // Given: 註冊帶有靜態 path rewrite 的規則
        add_filter('reverse_proxy_rules', function ($rules) {
            $rules[] = [
                'source' => '/legacy-api/*',
                'target' => 'https://new-api.example.com',
                'rewrite' => '/api/v2/$1',
            ];

            return $rules;
        });

        // And: Mock 後端回應
        $this->mockClient->addResponse(
            new Response(200, [], '{"migrated":true}')
        );

        // When: 請求 /legacy-api/users
        ob_start();
        $this->go_to('/legacy-api/users');
        ob_get_clean();

        // Then: 應該代理到 /api/v2/users
        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertNotFalse($lastRequest);
        $this->assertEquals(
            'https://new-api.example.com/api/v2/users',
            (string) $lastRequest->getUri()
        );
    }

    public function test_it_sets_host_header_to_target_by_default()
    {
        // Given: 註冊代理規則
        add_filter('reverse_proxy_rules', function ($rules) {
            $rules[] = [
                'source' => '/api/*',
                'target' => 'https://backend.example.com',
            ];

            return $rules;
        });

        // And: Mock 後端回應
        $this->mockClient->addResponse(new Response(200, [], '{}'));

        // When: 請求 /api/users
        ob_start();
        $this->go_to('/api/users');
        ob_get_clean();

        // Then: Host header 應該設置為目標主機
        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertNotFalse($lastRequest);
        $this->assertEquals('backend.example.com', $lastRequest->getHeaderLine('Host'));
    }

    public function test_it_preserves_original_host_when_configured()
    {
        // Given: 設置原始 Host
        $_SERVER['HTTP_HOST'] = 'original.example.com';

        // And: 註冊代理規則，保留原始 Host
        add_filter('reverse_proxy_rules', function ($rules) {
            $rules[] = [
                'source' => '/api/*',
                'target' => 'https://backend.example.com',
                'preserve_host' => true,
            ];

            return $rules;
        });

        // And: Mock 後端回應
        $this->mockClient->addResponse(new Response(200, [], '{}'));

        // When: 請求 /api/users
        ob_start();
        $this->go_to('/api/users');
        ob_get_clean();

        // Then: Host header 應該保留原始值
        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertNotFalse($lastRequest);
        $this->assertEquals('original.example.com', $lastRequest->getHeaderLine('Host'));
    }

    public function test_it_logs_proxy_request()
    {
        // Given: 註冊代理規則
        add_filter('reverse_proxy_rules', function ($rules) {
            $rules[] = [
                'source' => '/api/*',
                'target' => 'https://backend.example.com',
            ];

            return $rules;
        });

        // And: Mock 後端回應
        $this->mockClient->addResponse(new Response(200, [], '{}'));

        // And: 捕獲日誌
        $logEntries = [];
        add_action('reverse_proxy_log', function ($level, $message, $context) use (&$logEntries) {
            $logEntries[] = compact('level', 'message', 'context');
        }, 10, 3);

        // When: 請求 /api/users
        ob_start();
        $this->go_to('/api/users');
        ob_get_clean();

        // Then: 應該有日誌記錄
        $this->assertNotEmpty($logEntries);

        // And: 應該記錄請求資訊
        $requestLog = array_filter($logEntries, fn($e) => strpos($e['message'], 'Proxying') !== false);
        $this->assertNotEmpty($requestLog);
    }

    public function test_it_logs_proxy_error()
    {
        // Given: 註冊代理規則
        add_filter('reverse_proxy_rules', function ($rules) {
            $rules[] = [
                'source' => '/api/*',
                'target' => 'https://backend.example.com',
            ];

            return $rules;
        });

        // And: Mock 連線錯誤
        $this->mockClient->addException(
            new NetworkException('Connection refused', new Request('GET', 'https://backend.example.com/api/users'))
        );

        // And: 捕獲日誌
        $logEntries = [];
        add_action('reverse_proxy_log', function ($level, $message, $context) use (&$logEntries) {
            $logEntries[] = compact('level', 'message', 'context');
        }, 10, 3);

        // When: 請求 /api/users
        ob_start();
        $this->go_to('/api/users');
        ob_get_clean();

        // Then: 應該有錯誤日誌
        $errorLog = array_filter($logEntries, fn($e) => $e['level'] === 'error');
        $this->assertNotEmpty($errorLog);
    }
}
