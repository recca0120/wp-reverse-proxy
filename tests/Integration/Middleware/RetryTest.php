<?php

namespace Recca0120\ReverseProxy\Tests\Integration\Middleware;

use Http\Client\Exception\NetworkException;
use Http\Mock\Client as MockClient;
use Nyholm\Psr7\Request;
use Nyholm\Psr7\Response;
use Recca0120\ReverseProxy\Middleware\Retry;
use Recca0120\ReverseProxy\Routing\Route;
use WP_UnitTestCase;

class RetryTest extends WP_UnitTestCase
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

    public function test_succeeds_on_first_try()
    {
        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com', [
                new Retry(3),
            ]),
        ]);
        $this->mockClient->addResponse(new Response(200, [], '{"data":"success"}'));

        $output = $this->whenRequesting('/api/users');

        $this->assertEquals('{"data":"success"}', $output);
        $this->assertCount(1, $this->mockClient->getRequests());
    }

    public function test_retries_on_network_error()
    {
        // 停用預設的 ErrorHandling 讓 Retry 能接住錯誤
        add_filter('reverse_proxy_global_middlewares', '__return_empty_array');

        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com', [
                new Retry(3),
            ]),
        ]);

        // 前兩次失敗，第三次成功
        $this->mockClient->addException(new NetworkException('Connection failed', new Request('GET', 'https://backend.example.com/api/users')));
        $this->mockClient->addException(new NetworkException('Connection failed', new Request('GET', 'https://backend.example.com/api/users')));
        $this->mockClient->addResponse(new Response(200, [], '{"data":"success after retry"}'));

        $output = $this->whenRequesting('/api/users');

        $this->assertEquals('{"data":"success after retry"}', $output);
        $this->assertCount(3, $this->mockClient->getRequests());
    }

    public function test_retries_on_5xx_error()
    {
        add_filter('reverse_proxy_global_middlewares', '__return_empty_array');

        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com', [
                new Retry(3),
            ]),
        ]);

        // 前兩次 503，第三次成功
        $this->mockClient->addResponse(new Response(503, [], '{"error":"Service Unavailable"}'));
        $this->mockClient->addResponse(new Response(502, [], '{"error":"Bad Gateway"}'));
        $this->mockClient->addResponse(new Response(200, [], '{"data":"recovered"}'));

        $output = $this->whenRequesting('/api/users');

        $this->assertEquals('{"data":"recovered"}', $output);
        $this->assertCount(3, $this->mockClient->getRequests());
    }

    public function test_does_not_retry_on_4xx_error()
    {
        add_filter('reverse_proxy_global_middlewares', '__return_empty_array');

        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com', [
                new Retry(3),
            ]),
        ]);

        $this->mockClient->addResponse(new Response(404, [], '{"error":"Not Found"}'));

        $output = $this->whenRequesting('/api/users/999');

        $this->assertEquals('{"error":"Not Found"}', $output);
        $this->assertCount(1, $this->mockClient->getRequests());
    }

    public function test_gives_up_after_max_retries()
    {
        add_filter('reverse_proxy_global_middlewares', '__return_empty_array');

        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com', [
                new Retry(2),
            ]),
        ]);

        // 全部失敗
        $this->mockClient->addResponse(new Response(503, [], '{"error":"fail1"}'));
        $this->mockClient->addResponse(new Response(503, [], '{"error":"fail2"}'));

        $capturedResponse = null;
        add_filter('reverse_proxy_response', function ($response) use (&$capturedResponse) {
            $capturedResponse = $response;

            return $response;
        });

        $this->whenRequesting('/api/users');

        $this->assertNotNull($capturedResponse);
        $this->assertEquals(503, $capturedResponse->getStatusCode());
        $this->assertCount(2, $this->mockClient->getRequests());
    }

    public function test_only_retries_get_requests_by_default()
    {
        add_filter('reverse_proxy_global_middlewares', '__return_empty_array');

        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com', [
                new Retry(3),
            ]),
        ]);

        $_SERVER['REQUEST_METHOD'] = 'POST';

        $this->mockClient->addResponse(new Response(503, [], '{"error":"Service Unavailable"}'));

        $capturedResponse = null;
        add_filter('reverse_proxy_response', function ($response) use (&$capturedResponse) {
            $capturedResponse = $response;

            return $response;
        });

        $this->whenRequesting('/api/users');

        // POST 不應該重試
        $this->assertCount(1, $this->mockClient->getRequests());
        $this->assertEquals(503, $capturedResponse->getStatusCode());
    }

    public function test_can_retry_idempotent_methods()
    {
        add_filter('reverse_proxy_global_middlewares', '__return_empty_array');

        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com', [
                new Retry(3, ['GET', 'PUT', 'DELETE']),
            ]),
        ]);

        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $this->mockClient->addResponse(new Response(503, [], '{"error":"fail"}'));
        $this->mockClient->addResponse(new Response(200, [], '{"data":"success"}'));

        $output = $this->whenRequesting('/api/users/1');

        $this->assertEquals('{"data":"success"}', $output);
        $this->assertCount(2, $this->mockClient->getRequests());
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
}
