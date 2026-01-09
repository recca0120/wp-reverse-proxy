<?php

namespace Recca0120\ReverseProxy\Tests\Integration\Middleware;

use Http\Client\Exception\NetworkException;
use Http\Mock\Client as MockClient;
use Nyholm\Psr7\Request;
use Nyholm\Psr7\Response;
use Recca0120\ReverseProxy\Routing\Route;
use WP_UnitTestCase;

class ErrorHandlingTest extends WP_UnitTestCase
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

    public function test_it_passes_through_successful_requests()
    {
        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com'),
        ]);
        $this->mockClient->addResponse(new Response(200, [], '{"success":true}'));

        $output = $this->whenRequesting('/api/users');

        $this->assertEquals('{"success":true}', $output);
    }

    public function test_it_returns_502_on_network_error()
    {
        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com'),
        ]);
        $this->mockClient->addException(
            new NetworkException('Connection refused', new Request('GET', 'https://backend.example.com/api/users'))
        );

        $capturedResponse = null;
        add_filter('reverse_proxy_response', function ($response) use (&$capturedResponse) {
            $capturedResponse = $response;

            return $response;
        });

        $this->whenRequesting('/api/users');

        $this->assertNotNull($capturedResponse);
        $this->assertEquals(502, $capturedResponse->getStatusCode());
    }

    public function test_502_response_contains_error_message()
    {
        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com'),
        ]);
        $this->mockClient->addException(
            new NetworkException('Connection timeout', new Request('GET', 'https://backend.example.com/api/users'))
        );

        $capturedResponse = null;
        add_filter('reverse_proxy_response', function ($response) use (&$capturedResponse) {
            $capturedResponse = $response;

            return $response;
        });

        $this->whenRequesting('/api/users');

        $body = json_decode((string) $capturedResponse->getBody(), true);
        $this->assertStringContainsString('Bad Gateway', $body['error']);
        $this->assertEquals(502, $body['status']);
    }

    public function test_error_handling_is_enabled_by_default()
    {
        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com'),
        ]);
        $this->mockClient->addException(
            new NetworkException('Network error', new Request('GET', 'https://backend.example.com/api/users'))
        );

        $capturedResponse = null;
        add_filter('reverse_proxy_response', function ($response) use (&$capturedResponse) {
            $capturedResponse = $response;

            return $response;
        });

        $this->whenRequesting('/api/users');

        // Should not throw, should return 502
        $this->assertEquals(502, $capturedResponse->getStatusCode());
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
