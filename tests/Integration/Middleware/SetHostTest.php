<?php

namespace Recca0120\ReverseProxy\Tests\Integration\Middleware;

use Http\Mock\Client as MockClient;
use Nyholm\Psr7\Response;
use Recca0120\ReverseProxy\Middleware\SetHost;
use Recca0120\ReverseProxy\Routing\Route;
use Recca0120\ReverseProxy\Routing\RouteCollection;
use WP_UnitTestCase;

class SetHostTest extends WP_UnitTestCase
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

    public function test_it_sets_custom_host_header()
    {
        $this->givenRoutes([
            new Route('/api/*', 'https://127.0.0.1:8080', [
                new SetHost('api.example.com'),
            ]),
        ]);
        $this->mockClient->addResponse(new Response(200, [], '{"data":"test"}'));

        $this->whenRequesting('/api/users');

        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertEquals('api.example.com', $lastRequest->getHeaderLine('Host'));
    }

    public function test_it_overwrites_existing_host_header()
    {
        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com', [
                new SetHost('custom-host.com'),
            ]),
        ]);
        $this->mockClient->addResponse(new Response(200, [], '{}'));

        $this->whenRequesting('/api/users');

        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertEquals('custom-host.com', $lastRequest->getHeaderLine('Host'));
    }

    public function test_request_still_goes_to_correct_target()
    {
        $this->givenRoutes([
            new Route('/api/*', 'https://127.0.0.1:8080', [
                new SetHost('api.example.com'),
            ]),
        ]);
        $this->mockClient->addResponse(new Response(200, [], '{"proxied":true}'));

        $output = $this->whenRequesting('/api/data');

        $this->assertEquals('{"proxied":true}', $output);

        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertStringContainsString('127.0.0.1:8080', (string) $lastRequest->getUri());
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
