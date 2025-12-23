<?php

namespace ReverseProxy\Tests\Integration;

use Http\Mock\Client as MockClient;
use Nyholm\Psr7\Factory\Psr17Factory;
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
}
