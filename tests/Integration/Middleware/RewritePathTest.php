<?php

namespace Recca0120\ReverseProxy\Tests\Integration\Middleware;

use Http\Mock\Client as MockClient;
use Nyholm\Psr7\Response;
use Recca0120\ReverseProxy\Middleware\RewritePath;
use Recca0120\ReverseProxy\Routing\Route;
use WP_UnitTestCase;

class RewritePathTest extends WP_UnitTestCase
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

    public function test_it_rewrites_path_with_single_wildcard()
    {
        $this->givenRoutes([
            new Route('/api/v1/*', 'https://backend.example.com', [
                new RewritePath('/v1/$1'),
            ]),
        ]);
        $this->mockClient->addResponse(new Response(200, [], '{}'));

        $this->whenRequesting('/api/v1/users');

        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertEquals('/v1/users', $lastRequest->getUri()->getPath());
    }

    public function test_it_rewrites_path_with_multiple_wildcards()
    {
        $this->givenRoutes([
            new Route('/api/*/posts/*', 'https://backend.example.com', [
                new RewritePath('/v2/$1/items/$2'),
            ]),
        ]);
        $this->mockClient->addResponse(new Response(200, [], '{}'));

        $this->whenRequesting('/api/users/posts/123');

        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertEquals('/v2/users/items/123', $lastRequest->getUri()->getPath());
    }

    public function test_it_handles_empty_replacement()
    {
        $this->givenRoutes([
            new Route('/legacy/*', 'https://backend.example.com', [
                new RewritePath('/$1'),
            ]),
        ]);
        $this->mockClient->addResponse(new Response(200, [], '{}'));

        $this->whenRequesting('/legacy/api/users');

        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertEquals('/api/users', $lastRequest->getUri()->getPath());
    }

    public function test_response_is_returned_correctly()
    {
        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com', [
                new RewritePath('/v2/$1'),
            ]),
        ]);
        $this->mockClient->addResponse(new Response(200, [], '{"rewritten":true}'));

        $output = $this->whenRequesting('/api/data');

        $this->assertEquals('{"rewritten":true}', $output);
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
