<?php

namespace Recca0120\ReverseProxy\Tests\Integration\Middleware;

use Http\Mock\Client as MockClient;
use Nyholm\Psr7\Response;
use Recca0120\ReverseProxy\Middleware\IpFilter;
use Recca0120\ReverseProxy\Routing\Route;
use WP_UnitTestCase;

class IpFilterTest extends WP_UnitTestCase
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
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        parent::tearDown();
    }

    public function test_it_allows_ip_in_whitelist()
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';

        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com', [
                IpFilter::allow('192.168.1.100', '10.0.0.1'),
            ]),
        ]);
        $this->givenResponse(new Response(200, [], '{"data":"test"}'));

        $output = $this->whenRequesting('/api/users');

        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertNotFalse($lastRequest);
        $this->assertEquals('{"data":"test"}', $output);
    }

    public function test_it_blocks_ip_not_in_whitelist()
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.200';

        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com', [
                IpFilter::allow('192.168.1.100'),
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
        $this->assertFalse($lastRequest, 'Should not proxy blocked IP');
        $this->assertNotNull($capturedResponse);
        $this->assertEquals(403, $capturedResponse->getStatusCode());
    }

    public function test_it_blocks_ip_in_blacklist()
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';

        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com', [
                IpFilter::deny('192.168.1.100', '10.0.0.1'),
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
        $this->assertFalse($lastRequest, 'Should not proxy blocked IP');
        $this->assertNotNull($capturedResponse);
        $this->assertEquals(403, $capturedResponse->getStatusCode());
    }

    public function test_it_allows_ip_not_in_blacklist()
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.200';

        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com', [
                IpFilter::deny('192.168.1.100'),
            ]),
        ]);
        $this->givenResponse(new Response(200, [], '{"data":"test"}'));

        $output = $this->whenRequesting('/api/users');

        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertNotFalse($lastRequest);
        $this->assertEquals('{"data":"test"}', $output);
    }

    public function test_it_supports_cidr_notation_in_whitelist()
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.50';

        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com', [
                IpFilter::allow('192.168.1.0/24'),
            ]),
        ]);
        $this->givenResponse(new Response(200, [], '{"data":"test"}'));

        $output = $this->whenRequesting('/api/users');

        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertNotFalse($lastRequest);
        $this->assertEquals('{"data":"test"}', $output);
    }

    public function test_it_blocks_ip_outside_cidr_range()
    {
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';

        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com', [
                IpFilter::allow('192.168.1.0/24'),
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
        $this->assertFalse($lastRequest);
        $this->assertEquals(403, $capturedResponse->getStatusCode());
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
}
