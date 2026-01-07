<?php

namespace Recca0120\ReverseProxy\Tests\Integration\Middleware;

use Http\Mock\Client as MockClient;
use Nyholm\Psr7\Response;
use Recca0120\ReverseProxy\Middleware\AllowMethods;
use Recca0120\ReverseProxy\Route;
use WP_UnitTestCase;

class AllowMethodsTest extends WP_UnitTestCase
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

    public function test_it_allows_get_request()
    {
        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com', [
                new AllowMethods(['GET', 'POST']),
            ]),
        ]);
        $this->mockClient->addResponse(new Response(200, [], '{"data":"success"}'));

        $output = $this->whenRequesting('/api/users');

        $this->assertEquals('{"data":"success"}', $output);
    }

    public function test_it_allows_post_request()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com', [
                new AllowMethods(['GET', 'POST']),
            ]),
        ]);
        $this->mockClient->addResponse(new Response(201, [], '{"id":1}'));

        $capturedResponse = null;
        add_filter('reverse_proxy_response', function ($response) use (&$capturedResponse) {
            $capturedResponse = $response;

            return $response;
        });

        $this->whenRequesting('/api/users');

        $this->assertNotNull($capturedResponse);
        $this->assertEquals(201, $capturedResponse->getStatusCode());
    }

    public function test_it_returns_405_for_disallowed_method()
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';

        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com', [
                new AllowMethods(['GET', 'POST']),
            ]),
        ]);

        $capturedResponse = null;
        add_filter('reverse_proxy_response', function ($response) use (&$capturedResponse) {
            $capturedResponse = $response;

            return $response;
        });

        $this->whenRequesting('/api/users/1');

        $this->assertNotNull($capturedResponse);
        $this->assertEquals(405, $capturedResponse->getStatusCode());
        $this->assertEquals('GET, POST', $capturedResponse->getHeaderLine('Allow'));
    }

    public function test_it_allows_options_for_cors_preflight()
    {
        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';

        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com', [
                new AllowMethods(['GET', 'POST']),
            ]),
        ]);
        $this->mockClient->addResponse(new Response(200, [], ''));

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
