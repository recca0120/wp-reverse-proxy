<?php

namespace Recca0120\ReverseProxy\Tests\Integration\Middleware;

use Http\Mock\Client as MockClient;
use Nyholm\Psr7\Response;
use Recca0120\ReverseProxy\Middleware\Fallback;
use Recca0120\ReverseProxy\Routing\Route;
use Recca0120\ReverseProxy\Routing\RouteCollection;
use WP_UnitTestCase;

class FallbackTest extends WP_UnitTestCase
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

    public function test_it_passes_through_non_matching_status_codes()
    {
        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com', [
                new Fallback(404),
            ]),
        ]);
        $this->mockClient->addResponse(new Response(200, [], '{"data":"success"}'));

        $output = $this->whenRequesting('/api/users');

        $this->assertEquals('{"data":"success"}', $output);
    }

    public function test_it_returns_null_response_on_404()
    {
        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com', [
                new Fallback(404),
            ]),
        ]);
        $this->mockClient->addResponse(new Response(404, [], '{"error":"Not Found"}'));

        $capturedResponse = null;
        add_filter('reverse_proxy_response', function ($response) use (&$capturedResponse) {
            $capturedResponse = $response;

            return $response;
        });

        $this->whenRequesting('/api/users');

        $this->assertNull($capturedResponse);
    }

    public function test_it_supports_multiple_fallback_status_codes()
    {
        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com', [
                new Fallback(404, 410, 451),
            ]),
        ]);
        $this->mockClient->addResponse(new Response(410, [], '{"error":"Gone"}'));

        $capturedResponse = null;
        add_filter('reverse_proxy_response', function ($response) use (&$capturedResponse) {
            $capturedResponse = $response;

            return $response;
        });

        $this->whenRequesting('/api/old-resource');

        $this->assertNull($capturedResponse);
    }

    public function test_it_does_not_fallback_on_non_matching_status()
    {
        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com', [
                new Fallback(404),
            ]),
        ]);
        $this->mockClient->addResponse(new Response(500, [], '{"error":"Server Error"}'));

        $capturedResponse = null;
        add_filter('reverse_proxy_response', function ($response) use (&$capturedResponse) {
            $capturedResponse = $response;

            return $response;
        });

        $this->whenRequesting('/api/users');

        $this->assertNotNull($capturedResponse);
        $this->assertEquals(500, $capturedResponse->getStatusCode());
    }

    public function test_it_defaults_to_404_only()
    {
        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com', [
                new Fallback(),
            ]),
        ]);
        $this->mockClient->addResponse(new Response(404, [], '{"error":"Not Found"}'));

        $capturedResponse = null;
        add_filter('reverse_proxy_response', function ($response) use (&$capturedResponse) {
            $capturedResponse = $response;

            return $response;
        });

        $this->whenRequesting('/api/users');

        $this->assertNull($capturedResponse);
    }

    public function test_wordpress_continues_to_handle_on_fallback()
    {
        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com', [
                new Fallback(404),
            ]),
        ]);
        $this->mockClient->addResponse(new Response(404, [], '{"error":"Not Found"}'));

        // 當 fallback 時，不應該有任何輸出（讓 WordPress 處理）
        $output = $this->whenRequesting('/api/nonexistent');

        // 輸出應為空（沒有 proxy 回應）
        $this->assertEmpty($output);
    }

    private function givenRoutes(array $routeArray): void
    {
        add_filter('reverse_proxy_routes', function () use ($routeArray) {
            $routes = new RouteCollection();
            foreach ($routeArray as $route) {
                $routes->add($route);
            }

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
