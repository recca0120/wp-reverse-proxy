<?php

namespace ReverseProxy\Tests\Integration\Middleware;

use Http\Mock\Client as MockClient;
use Nyholm\Psr7\Response;
use ReverseProxy\Middleware\RateLimiting;
use ReverseProxy\Route;
use WP_UnitTestCase;

class RateLimitingTest extends WP_UnitTestCase
{
    /** @var MockClient */
    private $mockClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockClient = new MockClient;

        add_filter('reverse_proxy_http_client', function () {
            return $this->mockClient;
        });

        add_filter('reverse_proxy_should_exit', '__return_false');

        // 清除 rate limit 快取
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_rp_rate_%'");
    }

    protected function tearDown(): void
    {
        remove_all_filters('reverse_proxy_routes');
        remove_all_filters('reverse_proxy_http_client');
        remove_all_filters('reverse_proxy_should_exit');
        remove_all_filters('reverse_proxy_response');
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        parent::tearDown();
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

    public function test_it_allows_requests_within_limit()
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';

        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com', [
                new RateLimiting(10, 60), // 10 requests per 60 seconds
            ]),
        ]);
        $this->givenResponse(new Response(200, [], '{"data":"test"}'));

        $output = $this->whenRequesting('/api/users');

        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertNotFalse($lastRequest);
        $this->assertEquals('{"data":"test"}', $output);
    }

    public function test_it_adds_rate_limit_headers_to_response()
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.101';

        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com', [
                new RateLimiting(100, 60),
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
        $this->assertEquals('100', $capturedResponse->getHeaderLine('X-RateLimit-Limit'));
        $this->assertEquals('99', $capturedResponse->getHeaderLine('X-RateLimit-Remaining'));
        $this->assertNotEmpty($capturedResponse->getHeaderLine('X-RateLimit-Reset'));
    }

    public function test_it_blocks_requests_exceeding_limit()
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.102';

        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com', [
                new RateLimiting(2, 60), // 2 requests per 60 seconds
            ]),
        ]);

        // 第一次請求
        $this->givenResponse(new Response(200, [], '{"count":1}'));
        $this->whenRequesting('/api/users');

        // 第二次請求
        $this->givenResponse(new Response(200, [], '{"count":2}'));
        $this->whenRequesting('/api/users');

        // 第三次請求應該被阻擋
        $capturedResponse = null;
        add_filter('reverse_proxy_response', function ($response) use (&$capturedResponse) {
            $capturedResponse = $response;

            return $response;
        });

        $output = $this->whenRequesting('/api/users');

        $this->assertNotNull($capturedResponse);
        $this->assertEquals(429, $capturedResponse->getStatusCode());
        $this->assertNotEmpty($capturedResponse->getHeaderLine('Retry-After'));
    }

    public function test_it_tracks_rate_limit_per_ip()
    {
        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com', [
                new RateLimiting(1, 60),
            ]),
        ]);

        // IP 1 的請求
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        $this->givenResponse(new Response(200, [], '{"ip":"1"}'));
        $this->whenRequesting('/api/users');

        // IP 2 的請求（應該不受影響）
        $_SERVER['REMOTE_ADDR'] = '192.168.1.2';
        $this->givenResponse(new Response(200, [], '{"ip":"2"}'));

        $capturedResponse = null;
        add_filter('reverse_proxy_response', function ($response) use (&$capturedResponse) {
            $capturedResponse = $response;

            return $response;
        });

        $this->whenRequesting('/api/users');

        $this->assertNotNull($capturedResponse);
        $this->assertEquals(200, $capturedResponse->getStatusCode());
    }

    public function test_it_uses_custom_key_generator()
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.103';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer user123';

        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com', [
                new RateLimiting(10, 60, function ($request) {
                    // 用 Authorization header 作為 key
                    return md5($request->getHeaderLine('Authorization'));
                }),
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
        $this->assertEquals(200, $capturedResponse->getStatusCode());
    }
}
