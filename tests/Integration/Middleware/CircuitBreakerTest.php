<?php

namespace Recca0120\ReverseProxy\Tests\Integration\Middleware;

use Http\Client\Exception\NetworkException;
use Http\Mock\Client as MockClient;
use Nyholm\Psr7\Request;
use Nyholm\Psr7\Response;
use Recca0120\ReverseProxy\Middleware\CircuitBreaker;
use Recca0120\ReverseProxy\Routing\Route;
use Recca0120\ReverseProxy\Tests\Stubs\ArrayCache;
use WP_UnitTestCase;

class CircuitBreakerTest extends WP_UnitTestCase
{
    /** @var MockClient */
    private $mockClient;

    /** @var ArrayCache */
    private $cache;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockClient = new MockClient();
        $this->cache = new ArrayCache();

        add_filter('reverse_proxy_http_client', function () {
            return $this->mockClient;
        });

        add_filter('reverse_proxy_should_exit', '__return_false');

        // 清除 circuit breaker 狀態
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_rp_cb_%'");
    }

    protected function tearDown(): void
    {
        remove_all_filters('reverse_proxy_routes');
        remove_all_filters('reverse_proxy_http_client');
        remove_all_filters('reverse_proxy_should_exit');
        remove_all_filters('reverse_proxy_response');
        remove_all_filters('reverse_proxy_global_middlewares');
        $_SERVER['REQUEST_METHOD'] = 'GET';
        parent::tearDown();
    }

    public function test_allows_requests_when_circuit_is_closed()
    {
        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com', [
                $this->createCircuitBreaker('test-service', 3, 60),
            ]),
        ]);
        $this->mockClient->addResponse(new Response(200, [], '{"data":"success"}'));

        $output = $this->whenRequesting('/api/users');

        $this->assertEquals('{"data":"success"}', $output);
    }

    public function test_opens_circuit_after_failure_threshold()
    {
        add_filter('reverse_proxy_global_middlewares', '__return_empty_array');

        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com', [
                $this->createCircuitBreaker('threshold-test', 3, 60),
            ]),
        ]);

        // 3 次失敗達到閾值
        for ($i = 0; $i < 3; $i++) {
            $this->mockClient->addResponse(new Response(503, [], '{"error":"fail"}'));
            $this->whenRequesting('/api/users');
        }

        // 第 4 次請求應該直接失敗（circuit open）
        $capturedResponse = null;
        add_filter('reverse_proxy_response', function ($response) use (&$capturedResponse) {
            $capturedResponse = $response;

            return $response;
        });

        $this->whenRequesting('/api/users');

        $this->assertNotNull($capturedResponse);
        $this->assertEquals(503, $capturedResponse->getStatusCode());
        $this->assertStringContainsString('Circuit breaker is open', (string) $capturedResponse->getBody());

        // 只應該發出 3 次實際請求
        $this->assertCount(3, $this->mockClient->getRequests());
    }

    public function test_resets_failure_count_on_success()
    {
        add_filter('reverse_proxy_global_middlewares', '__return_empty_array');

        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com', [
                $this->createCircuitBreaker('reset-test', 3, 60),
            ]),
        ]);

        // 2 次失敗
        $this->mockClient->addResponse(new Response(503, [], '{"error":"fail"}'));
        $this->whenRequesting('/api/users');
        $this->mockClient->addResponse(new Response(503, [], '{"error":"fail"}'));
        $this->whenRequesting('/api/users');

        // 1 次成功（重置計數）
        $this->mockClient->addResponse(new Response(200, [], '{"data":"success"}'));
        $this->whenRequesting('/api/users');

        // 再 2 次失敗（不應該開啟 circuit）
        $this->mockClient->addResponse(new Response(503, [], '{"error":"fail"}'));
        $this->whenRequesting('/api/users');
        $this->mockClient->addResponse(new Response(503, [], '{"error":"fail"}'));
        $this->whenRequesting('/api/users');

        // 這次請求應該還是正常發出
        $this->mockClient->addResponse(new Response(200, [], '{"data":"ok"}'));
        $output = $this->whenRequesting('/api/users');

        $this->assertEquals('{"data":"ok"}', $output);
        $this->assertCount(6, $this->mockClient->getRequests());
    }

    public function test_counts_network_errors_as_failures()
    {
        add_filter('reverse_proxy_global_middlewares', '__return_empty_array');

        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com', [
                $this->createCircuitBreaker('network-test', 2, 60),
            ]),
        ]);

        // 2 次網路錯誤
        $this->mockClient->addException(new NetworkException('Connection failed', new Request('GET', 'https://backend.example.com/api/users')));
        try {
            ob_start();
            $this->go_to('/api/users');
            ob_end_clean();
        } catch (\Exception $e) {
            ob_end_clean();
        }

        $this->mockClient->addException(new NetworkException('Connection failed', new Request('GET', 'https://backend.example.com/api/users')));
        try {
            ob_start();
            $this->go_to('/api/users');
            ob_end_clean();
        } catch (\Exception $e) {
            ob_end_clean();
        }

        // 第 3 次請求應該 circuit open
        $capturedResponse = null;
        add_filter('reverse_proxy_response', function ($response) use (&$capturedResponse) {
            $capturedResponse = $response;

            return $response;
        });

        $this->whenRequesting('/api/users');

        $this->assertNotNull($capturedResponse);
        $this->assertEquals(503, $capturedResponse->getStatusCode());
    }

    public function test_different_services_have_separate_circuits()
    {
        add_filter('reverse_proxy_global_middlewares', '__return_empty_array');

        $this->givenRoutes([
            new Route('/api/a/*', 'https://backend-a.example.com', [
                $this->createCircuitBreaker('service-a', 2, 60),
            ]),
            new Route('/api/b/*', 'https://backend-b.example.com', [
                $this->createCircuitBreaker('service-b', 2, 60),
            ]),
        ]);

        // Service A 失敗 2 次，開啟 circuit
        $this->mockClient->addResponse(new Response(503, [], '{"error":"fail"}'));
        $this->whenRequesting('/api/a/users');
        $this->mockClient->addResponse(new Response(503, [], '{"error":"fail"}'));
        $this->whenRequesting('/api/a/users');

        // Service B 應該還是正常
        $this->mockClient->addResponse(new Response(200, [], '{"data":"service-b"}'));
        $output = $this->whenRequesting('/api/b/users');

        $this->assertEquals('{"data":"service-b"}', $output);
    }

    private function givenRoutes(array $routeArray): void
    {
        add_filter('reverse_proxy_routes', function ($routes) use ($routeArray) {
            return $routes->add($routeArray);

        });
    }

    private function whenRequesting(string $path): string
    {
        ob_start();
        $this->go_to($path);

        return ob_get_clean();
    }

    private function createCircuitBreaker(string $serviceName, int $threshold = 5, int $timeout = 60): CircuitBreaker
    {
        $middleware = new CircuitBreaker($serviceName, $threshold, $timeout);
        $middleware->setCache($this->cache);

        return $middleware;
    }
}
