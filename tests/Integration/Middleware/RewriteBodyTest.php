<?php

namespace Recca0120\ReverseProxy\Tests\Integration\Middleware;

use Http\Mock\Client as MockClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Recca0120\ReverseProxy\Middleware\RewriteBody;
use Recca0120\ReverseProxy\Route;
use WP_UnitTestCase;

class RewriteBodyTest extends WP_UnitTestCase
{
    /** @var MockClient */
    private $mockClient;

    /** @var Psr17Factory */
    private $streamFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockClient = new MockClient;
        $this->streamFactory = new Psr17Factory;

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

    public function test_it_rewrites_urls_in_html_response()
    {
        $this->givenRoutes([
            new Route('/page/*', 'https://example.com', [
                new RewriteBody(
                    ['#https://example\.com#' => 'https://my-wordpress.com'],
                    $this->streamFactory
                ),
            ]),
        ]);

        $responseBody = '<html><link href="https://example.com/style.css"><a href="https://example.com/about">About</a></html>';
        $this->mockClient->addResponse(
            (new Response(200))
                ->withHeader('Content-Type', 'text/html; charset=utf-8')
                ->withBody($this->streamFactory->createStream($responseBody))
        );

        $output = $this->whenRequesting('/page/home');

        $this->assertEquals(
            '<html><link href="https://my-wordpress.com/style.css"><a href="https://my-wordpress.com/about">About</a></html>',
            $output
        );
    }

    public function test_it_rewrites_urls_in_css_response()
    {
        $this->givenRoutes([
            new Route('/assets/*', 'https://cdn.example.com', [
                new RewriteBody(
                    ['#https://cdn\.example\.com#' => 'https://my-wordpress.com/cdn'],
                    $this->streamFactory
                ),
            ]),
        ]);

        $responseBody = 'body { background: url(https://cdn.example.com/images/bg.png); }';
        $this->mockClient->addResponse(
            (new Response(200))
                ->withHeader('Content-Type', 'text/css')
                ->withBody($this->streamFactory->createStream($responseBody))
        );

        $output = $this->whenRequesting('/assets/style.css');

        $this->assertEquals(
            'body { background: url(https://my-wordpress.com/cdn/images/bg.png); }',
            $output
        );
    }

    public function test_it_rewrites_urls_in_json_response()
    {
        $this->givenRoutes([
            new Route('/api/*', 'https://api.example.com', [
                new RewriteBody(
                    ['#https://api\.example\.com#' => 'https://my-wordpress.com/api'],
                    $this->streamFactory
                ),
            ]),
        ]);

        $responseBody = '{"next": "https://api.example.com/users?page=2", "data": []}';
        $this->mockClient->addResponse(
            (new Response(200))
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->streamFactory->createStream($responseBody))
        );

        $output = $this->whenRequesting('/api/users');

        $this->assertEquals(
            '{"next": "https://my-wordpress.com/api/users?page=2", "data": []}',
            $output
        );
    }

    public function test_it_does_not_rewrite_binary_content()
    {
        $this->givenRoutes([
            new Route('/images/*', 'https://example.com', [
                new RewriteBody(
                    ['#https://example\.com#' => 'https://my-wordpress.com'],
                    $this->streamFactory
                ),
            ]),
        ]);

        $binaryContent = 'PNG-BINARY-DATA-https://example.com';
        $this->mockClient->addResponse(
            (new Response(200))
                ->withHeader('Content-Type', 'image/png')
                ->withBody($this->streamFactory->createStream($binaryContent))
        );

        $output = $this->whenRequesting('/images/logo.png');

        $this->assertEquals($binaryContent, $output);
    }

    public function test_it_applies_multiple_replacements()
    {
        $this->givenRoutes([
            new Route('/page/*', 'https://example.com', [
                new RewriteBody(
                    [
                        '#https://example\.com#' => 'https://my-wordpress.com',
                        '#https://cdn\.example\.com#' => 'https://my-wordpress.com/cdn',
                        '#https://api\.example\.com#' => 'https://my-wordpress.com/api',
                    ],
                    $this->streamFactory
                ),
            ]),
        ]);

        $responseBody = '<script>const API="https://api.example.com";</script><link href="https://cdn.example.com/style.css"><a href="https://example.com">Home</a>';
        $this->mockClient->addResponse(
            (new Response(200))
                ->withHeader('Content-Type', 'text/html')
                ->withBody($this->streamFactory->createStream($responseBody))
        );

        $output = $this->whenRequesting('/page/home');

        $this->assertEquals(
            '<script>const API="https://my-wordpress.com/api";</script><link href="https://my-wordpress.com/cdn/style.css"><a href="https://my-wordpress.com">Home</a>',
            $output
        );
    }
}
