<?php

namespace Recca0120\ReverseProxy\Tests\Integration\Middleware;

use Http\Mock\Client as MockClient;
use Nyholm\Psr7\Response;
use Recca0120\ReverseProxy\Middleware\Caching;
use Recca0120\ReverseProxy\Route;
use WP_UnitTestCase;

class CachingTest extends WP_UnitTestCase
{
    /** @var MockClient */
    private $mockClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockClient = new MockClient();

        add_filter('reverse_proxy_http_client', function () {
            return $this->mockClient;
        });

        add_filter('reverse_proxy_should_exit', '__return_false');

        // 清除快取
        wp_cache_flush();
    }

    protected function tearDown(): void
    {
        remove_all_filters('reverse_proxy_routes');
        remove_all_filters('reverse_proxy_http_client');
        remove_all_filters('reverse_proxy_should_exit');
        remove_all_filters('reverse_proxy_response');
        $_SERVER['REQUEST_METHOD'] = 'GET';
        parent::tearDown();
    }

    public function test_it_caches_get_response()
    {
        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com', [
                new Caching(300),
            ]),
        ]);

        // 第一次請求
        $this->givenResponse(new Response(200, ['Content-Type' => 'application/json'], '{"data":"original"}'));
        $output1 = $this->whenRequesting('/api/users');
        $this->assertEquals('{"data":"original"}', $output1);

        // 第二次請求（應該從快取返回，不呼叫後端）
        // 不加入新的 response，如果呼叫後端會失敗
        $output2 = $this->whenRequesting('/api/users');
        $this->assertEquals('{"data":"original"}', $output2);

        // 驗證只呼叫了一次後端
        $requests = $this->mockClient->getRequests();
        $this->assertCount(1, $requests);
    }

    public function test_it_does_not_cache_post_requests()
    {
        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com', [
                new Caching(300),
            ]),
        ]);

        $_SERVER['REQUEST_METHOD'] = 'POST';

        // 第一次 POST 請求
        $this->givenResponse(new Response(201, [], '{"id":1}'));
        $output1 = $this->whenRequesting('/api/users');
        $this->assertEquals('{"id":1}', $output1);

        // 第二次 POST 請求（應該呼叫後端）
        $this->givenResponse(new Response(201, [], '{"id":2}'));
        $output2 = $this->whenRequesting('/api/users');
        $this->assertEquals('{"id":2}', $output2);

        // 驗證呼叫了兩次後端
        $requests = $this->mockClient->getRequests();
        $this->assertCount(2, $requests);
    }

    public function test_it_does_not_cache_non_200_responses()
    {
        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com', [
                new Caching(300),
            ]),
        ]);

        // 第一次請求返回 404
        $this->givenResponse(new Response(404, [], '{"error":"Not Found"}'));
        $output1 = $this->whenRequesting('/api/users/999');
        $this->assertEquals('{"error":"Not Found"}', $output1);

        // 第二次請求（應該呼叫後端）
        $this->givenResponse(new Response(200, [], '{"data":"found"}'));
        $output2 = $this->whenRequesting('/api/users/999');
        $this->assertEquals('{"data":"found"}', $output2);
    }

    public function test_it_adds_cache_headers_to_response()
    {
        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com', [
                new Caching(300),
            ]),
        ]);
        $this->givenResponse(new Response(200, [], '{"data":"test"}'));

        $capturedResponse = null;
        add_filter('reverse_proxy_response', function ($response) use (&$capturedResponse) {
            $capturedResponse = $response;

            return $response;
        });

        $this->whenRequesting('/api/users');

        $this->assertNotNull($capturedResponse);
        $this->assertEquals('MISS', $capturedResponse->getHeaderLine('X-Cache'));

        // 第二次請求
        remove_all_filters('reverse_proxy_response');
        add_filter('reverse_proxy_response', function ($response) use (&$capturedResponse) {
            $capturedResponse = $response;

            return $response;
        });

        $this->whenRequesting('/api/users');

        $this->assertEquals('HIT', $capturedResponse->getHeaderLine('X-Cache'));
    }

    public function test_it_respects_cache_control_no_cache()
    {
        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com', [
                new Caching(300),
            ]),
        ]);

        // 後端返回 no-cache
        $this->givenResponse(new Response(200, ['Cache-Control' => 'no-cache'], '{"data":"v1"}'));
        $this->whenRequesting('/api/users');

        // 第二次請求應該呼叫後端
        $this->givenResponse(new Response(200, ['Cache-Control' => 'no-cache'], '{"data":"v2"}'));
        $output = $this->whenRequesting('/api/users');

        $this->assertEquals('{"data":"v2"}', $output);
    }

    public function test_it_caches_by_uri_including_query_string()
    {
        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com', [
                new Caching(300),
            ]),
        ]);

        // 請求 page=1
        $this->givenResponse(new Response(200, [], '{"page":1}'));
        $output1 = $this->whenRequesting('/api/users?page=1');
        $this->assertEquals('{"page":1}', $output1);

        // 請求 page=2（不同的快取 key）
        $this->givenResponse(new Response(200, [], '{"page":2}'));
        $output2 = $this->whenRequesting('/api/users?page=2');
        $this->assertEquals('{"page":2}', $output2);

        // 再次請求 page=1（應該從快取）
        $output3 = $this->whenRequesting('/api/users?page=1');
        $this->assertEquals('{"page":1}', $output3);
    }

    private function givenRoutes(array $routes): void
    {
        add_filter('reverse_proxy_routes', function () use ($routes) {
            return $routes;
        });
    }

    private function givenResponse(Response $response): void
    {
        $this->mockClient->addResponse($response);
    }

    private function whenRequesting(string $path): string
    {
        ob_start();
        $this->go_to($path);

        return ob_get_clean();
    }
}
