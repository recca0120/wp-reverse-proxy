<?php

namespace ReverseProxy\Tests\Integration\Middleware;

use Http\Mock\Client as MockClient;
use Nyholm\Psr7\Response;
use ReverseProxy\Middleware\Cors;
use ReverseProxy\Route;
use WP_UnitTestCase;

class CorsTest extends WP_UnitTestCase
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
        unset($_SERVER['HTTP_ORIGIN']);
        unset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']);
        unset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']);
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

    public function test_it_adds_cors_headers_to_response()
    {
        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com', [
                new Cors(['https://example.com']),
            ]),
        ]);
        $this->givenResponse(new Response(200, [], '{"data":"test"}'));

        $_SERVER['HTTP_ORIGIN'] = 'https://example.com';

        $capturedResponse = null;
        add_filter('reverse_proxy_response', function ($response) use (&$capturedResponse) {
            $capturedResponse = $response;

            return $response;
        });

        $this->whenRequesting('/api/users');

        $this->assertNotNull($capturedResponse);
        $this->assertEquals('https://example.com', $capturedResponse->getHeaderLine('Access-Control-Allow-Origin'));
    }

    public function test_it_handles_preflight_options_request()
    {
        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com', [
                new Cors(['https://example.com']),
            ]),
        ]);

        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
        $_SERVER['HTTP_ORIGIN'] = 'https://example.com';
        $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] = 'Content-Type, Authorization';

        $capturedResponse = null;
        add_filter('reverse_proxy_response', function ($response) use (&$capturedResponse) {
            $capturedResponse = $response;

            return $response;
        });

        $this->whenRequesting('/api/users');

        $this->assertNotNull($capturedResponse);
        $this->assertEquals(204, $capturedResponse->getStatusCode());
        $this->assertEquals('https://example.com', $capturedResponse->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertNotEmpty($capturedResponse->getHeaderLine('Access-Control-Allow-Methods'));
        $this->assertNotEmpty($capturedResponse->getHeaderLine('Access-Control-Allow-Headers'));

        // Preflight 不應該呼叫後端
        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertFalse($lastRequest);
    }

    public function test_it_rejects_non_allowed_origin()
    {
        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com', [
                new Cors(['https://example.com']),
            ]),
        ]);
        $this->givenResponse(new Response(200, [], '{"data":"test"}'));

        $_SERVER['HTTP_ORIGIN'] = 'https://evil.com';

        $capturedResponse = null;
        add_filter('reverse_proxy_response', function ($response) use (&$capturedResponse) {
            $capturedResponse = $response;

            return $response;
        });

        $this->whenRequesting('/api/users');

        $this->assertNotNull($capturedResponse);
        $this->assertEmpty($capturedResponse->getHeaderLine('Access-Control-Allow-Origin'));
    }

    public function test_it_allows_wildcard_origin()
    {
        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com', [
                new Cors(['*']),
            ]),
        ]);
        $this->givenResponse(new Response(200, [], '{"data":"test"}'));

        $_SERVER['HTTP_ORIGIN'] = 'https://any-domain.com';

        $capturedResponse = null;
        add_filter('reverse_proxy_response', function ($response) use (&$capturedResponse) {
            $capturedResponse = $response;

            return $response;
        });

        $this->whenRequesting('/api/users');

        $this->assertNotNull($capturedResponse);
        $this->assertEquals('*', $capturedResponse->getHeaderLine('Access-Control-Allow-Origin'));
    }

    public function test_it_includes_credentials_header_when_enabled()
    {
        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com', [
                new Cors(['https://example.com'], ['GET', 'POST'], ['Content-Type'], true),
            ]),
        ]);
        $this->givenResponse(new Response(200, [], '{"data":"test"}'));

        $_SERVER['HTTP_ORIGIN'] = 'https://example.com';

        $capturedResponse = null;
        add_filter('reverse_proxy_response', function ($response) use (&$capturedResponse) {
            $capturedResponse = $response;

            return $response;
        });

        $this->whenRequesting('/api/users');

        $this->assertNotNull($capturedResponse);
        $this->assertEquals('true', $capturedResponse->getHeaderLine('Access-Control-Allow-Credentials'));
    }
}
