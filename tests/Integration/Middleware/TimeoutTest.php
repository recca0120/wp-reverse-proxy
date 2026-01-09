<?php

namespace Recca0120\ReverseProxy\Tests\Integration\Middleware;

use Http\Client\Exception\NetworkException;
use Http\Mock\Client as MockClient;
use Nyholm\Psr7\Request;
use Nyholm\Psr7\Response;
use Recca0120\ReverseProxy\Middleware\Timeout;
use Recca0120\ReverseProxy\Routing\Route;
use WP_UnitTestCase;

class TimeoutTest extends WP_UnitTestCase
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
        remove_all_filters('reverse_proxy_default_middlewares');
        $_SERVER['REQUEST_METHOD'] = 'GET';
        parent::tearDown();
    }

    public function test_it_passes_through_successful_requests()
    {
        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com', [
                new Timeout(30),
            ]),
        ]);
        $this->mockClient->addResponse(new Response(200, [], '{"data":"success"}'));

        $output = $this->whenRequesting('/api/users');

        $this->assertEquals('{"data":"success"}', $output);
    }

    public function test_it_returns_504_on_timeout_exception()
    {
        add_filter('reverse_proxy_default_middlewares', '__return_empty_array');

        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com', [
                new Timeout(5),
            ]),
        ]);

        // 模擬超時錯誤
        $this->mockClient->addException(new NetworkException('Connection timed out', new Request('GET', 'https://backend.example.com/api/users')));

        $capturedResponse = null;
        add_filter('reverse_proxy_response', function ($response) use (&$capturedResponse) {
            $capturedResponse = $response;

            return $response;
        });

        $this->whenRequesting('/api/users');

        $this->assertNotNull($capturedResponse);
        $this->assertEquals(504, $capturedResponse->getStatusCode());
        $this->assertStringContainsString('Gateway Timeout', (string) $capturedResponse->getBody());
    }

    public function test_it_adds_timeout_header_to_request()
    {
        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com', [
                new Timeout(15),
            ]),
        ]);
        $this->mockClient->addResponse(new Response(200, [], '{}'));

        $this->whenRequesting('/api/users');

        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertNotFalse($lastRequest);
        $this->assertEquals('15', $lastRequest->getHeaderLine('X-Timeout'));
    }

    public function test_it_uses_default_timeout()
    {
        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com', [
                new Timeout(),
            ]),
        ]);
        $this->mockClient->addResponse(new Response(200, [], '{}'));

        $this->whenRequesting('/api/users');

        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertEquals('30', $lastRequest->getHeaderLine('X-Timeout'));
    }

    private function givenRoutes(array $routes): void
    {
        add_filter('reverse_proxy_routes', function () use ($routes) {
            return $routes;
        });
    }

    private function whenRequesting(string $path): string
    {
        ob_start();
        $this->go_to($path);

        return ob_get_clean();
    }
}
