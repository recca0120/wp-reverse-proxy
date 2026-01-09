<?php

namespace Recca0120\ReverseProxy\Tests\Integration\Middleware;

use Http\Mock\Client as MockClient;
use Nyholm\Psr7\Response;
use Recca0120\ReverseProxy\Middleware\ProxyHeaders;
use Recca0120\ReverseProxy\Routing\Route;
use WP_UnitTestCase;

class ProxyHeadersTest extends WP_UnitTestCase
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

        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';
        $_SERVER['SERVER_PORT'] = '443';
        $_SERVER['HTTPS'] = 'on';
    }

    protected function tearDown(): void
    {
        remove_all_filters('reverse_proxy_routes');
        remove_all_filters('reverse_proxy_http_client');
        remove_all_filters('reverse_proxy_should_exit');
        remove_all_filters('reverse_proxy_response');
        remove_all_filters('reverse_proxy_global_middlewares');
        unset($_SERVER['REMOTE_ADDR'], $_SERVER['SERVER_PORT'], $_SERVER['HTTPS'], $_SERVER['HTTP_X_REAL_IP']);
        $_SERVER['REQUEST_METHOD'] = 'GET';
        parent::tearDown();
    }

    public function test_it_preserves_existing_x_real_ip_header()
    {
        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com', [
                new ProxyHeaders(),
            ]),
        ]);
        $this->mockClient->addResponse(new Response(200, [], '{}'));

        // Add X-Real-IP to the incoming request
        $this->whenRequesting('/api/users', 'GET', ['HTTP_X_REAL_IP' => '1.2.3.4']);

        $lastRequest = $this->mockClient->getLastRequest();
        // X-Real-IP should be preserved, not overwritten
        $this->assertEquals('1.2.3.4', $lastRequest->getHeaderLine('X-Real-IP'));
    }

    public function test_it_does_not_add_x_real_ip_header_when_not_present()
    {
        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com', [
                new ProxyHeaders(),
            ]),
        ]);
        $this->mockClient->addResponse(new Response(200, [], '{}'));

        $this->whenRequesting('/api/users');

        $lastRequest = $this->mockClient->getLastRequest();
        // X-Real-IP should not be added by ProxyHeaders
        $this->assertEquals('', $lastRequest->getHeaderLine('X-Real-IP'));
    }

    public function test_it_adds_x_forwarded_for_header()
    {
        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com', [
                new ProxyHeaders(),
            ]),
        ]);
        $this->mockClient->addResponse(new Response(200, [], '{}'));

        $this->whenRequesting('/api/users');

        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertStringContainsString('192.168.1.100', $lastRequest->getHeaderLine('X-Forwarded-For'));
    }

    public function test_it_adds_x_forwarded_proto_header()
    {
        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com', [
                new ProxyHeaders(),
            ]),
        ]);
        $this->mockClient->addResponse(new Response(200, [], '{}'));

        $this->whenRequesting('/api/users');

        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertEquals('https', $lastRequest->getHeaderLine('X-Forwarded-Proto'));
    }

    public function test_it_adds_x_forwarded_port_header()
    {
        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com', [
                new ProxyHeaders(),
            ]),
        ]);
        $this->mockClient->addResponse(new Response(200, [], '{}'));

        $this->whenRequesting('/api/users');

        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertEquals('443', $lastRequest->getHeaderLine('X-Forwarded-Port'));
    }

    private function givenRoutes(array $routeArray): void
    {
        add_filter('reverse_proxy_routes', function ($routes) use ($routeArray) {
            return $routes->add($routeArray);

        });
    }

    private function whenRequesting(string $path, string $method = 'GET', array $headers = []): string
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        foreach ($headers as $key => $value) {
            $_SERVER[$key] = $value;
        }

        ob_start();
        $this->go_to($path);

        return ob_get_clean();
    }
}
