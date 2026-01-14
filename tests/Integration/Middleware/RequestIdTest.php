<?php

namespace Recca0120\ReverseProxy\Tests\Integration\Middleware;

use Http\Mock\Client as MockClient;
use Nyholm\Psr7\Response;
use Recca0120\ReverseProxy\Middleware\RequestId;
use Recca0120\ReverseProxy\Routing\Route;
use WP_UnitTestCase;

class RequestIdTest extends WP_UnitTestCase
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
        unset($_SERVER['HTTP_X_REQUEST_ID']);
        $_SERVER['REQUEST_METHOD'] = 'GET';
        parent::tearDown();
    }

    public function test_generates_request_id_and_forwards_to_backend()
    {
        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com', [
                new RequestId(),
            ]),
        ]);
        $this->givenResponse(new Response(200, [], '{"data":"test"}'));

        $this->whenRequesting('/api/users');

        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertNotFalse($lastRequest);
        $requestId = $lastRequest->getHeaderLine('X-Request-ID');
        $this->assertNotEmpty($requestId);
        $this->assertMatchesRegularExpression('/^[a-f0-9-]{36}$/', $requestId);
    }

    public function test_adds_request_id_to_response()
    {
        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com', [
                new RequestId(),
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
        $responseId = $capturedResponse->getHeaderLine('X-Request-ID');
        $this->assertNotEmpty($responseId);

        // Request 和 Response 的 ID 應該相同
        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertEquals($lastRequest->getHeaderLine('X-Request-ID'), $responseId);
    }

    public function test_preserves_existing_request_id_from_client()
    {
        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com', [
                new RequestId(),
            ]),
        ]);
        $this->givenResponse(new Response(200, [], '{"data":"test"}'));

        $_SERVER['HTTP_X_REQUEST_ID'] = 'existing-request-id-123';

        $this->whenRequesting('/api/users');

        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertNotFalse($lastRequest);
        $this->assertEquals('existing-request-id-123', $lastRequest->getHeaderLine('X-Request-ID'));
    }

    public function test_uses_custom_header_name()
    {
        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com', [
                new RequestId('X-Correlation-ID'),
            ]),
        ]);
        $this->givenResponse(new Response(200, [], '{"data":"test"}'));

        $capturedResponse = null;
        add_filter('reverse_proxy_response', function ($response) use (&$capturedResponse) {
            $capturedResponse = $response;

            return $response;
        });

        $this->whenRequesting('/api/users');

        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertNotFalse($lastRequest);
        $this->assertNotEmpty($lastRequest->getHeaderLine('X-Correlation-ID'));

        $this->assertNotNull($capturedResponse);
        $this->assertNotEmpty($capturedResponse->getHeaderLine('X-Correlation-ID'));
    }

    private function givenRoutes(array $routeArray): void
    {
        add_filter('reverse_proxy_routes', function ($routes) use ($routeArray) {
            return $routes->add($routeArray);

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
