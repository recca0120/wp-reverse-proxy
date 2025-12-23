<?php

namespace ReverseProxy\Tests\Integration;

use Http\Client\Exception\NetworkException;
use Http\Mock\Client as MockClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Request;
use Nyholm\Psr7\Response;
use ReverseProxy\Middleware\AllowMethodsMiddleware;
use ReverseProxy\Middleware\ProxyHeadersMiddleware;
use ReverseProxy\Middleware\RewritePathMiddleware;
use ReverseProxy\Middleware\SetHostMiddleware;
use ReverseProxy\ReverseProxy;
use ReverseProxy\Route;
use WP_UnitTestCase;

class ReverseProxyTest extends WP_UnitTestCase
{
    /** @var MockClient */
    private $mockClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockClient = new MockClient();

        // 注入 Mock Client 到插件
        add_filter('reverse_proxy_http_client', function () {
            return $this->mockClient;
        });

        // 測試時不要 exit
        add_filter('reverse_proxy_should_exit', '__return_false');
    }

    protected function tearDown(): void
    {
        remove_all_filters('reverse_proxy_rules');
        remove_all_filters('reverse_proxy_http_client');
        remove_all_filters('reverse_proxy_should_exit');
        parent::tearDown();
    }

    private function givenRules(array $rules): void
    {
        add_filter('reverse_proxy_rules', function () use ($rules) {
            return $rules;
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

    public function test_it_proxies_request_matching_rule_to_target_server()
    {
        $this->givenRules([new Route('/api/*', 'https://backend.example.com')]);
        $this->givenResponse(new Response(200, ['Content-Type' => 'application/json'], '{"message":"hello"}'));

        $output = $this->whenRequesting('/api/users');

        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertNotFalse($lastRequest, 'Should have made a proxy request');
        $this->assertEquals('GET', $lastRequest->getMethod());
        $this->assertEquals('https://backend.example.com/api/users', (string) $lastRequest->getUri());
        $this->assertEquals('{"message":"hello"}', $output);
    }

    public function test_it_does_not_proxy_request_not_matching_any_rule()
    {
        $this->givenRules([new Route('/api/*', 'https://backend.example.com')]);

        $this->go_to('/about');

        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertFalse($lastRequest, 'Should not have made a proxy request');
    }

    public function test_wordpress_continues_normally_for_non_matching_requests()
    {
        $post_id = $this->factory->post->create([
            'post_title' => 'Hello World',
            'post_status' => 'publish',
        ]);
        $this->givenRules([new Route('/api/*', 'https://backend.example.com')]);

        $this->go_to(get_permalink($post_id));

        $this->assertTrue(is_single());
        $this->assertEquals($post_id, get_the_ID());
        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertFalse($lastRequest);
    }

    public function test_it_forwards_post_request_with_body()
    {
        $this->givenRules([new Route('/api/*', 'https://backend.example.com')]);
        $this->givenResponse(new Response(201, ['Content-Type' => 'application/json'], '{"id":1}'));

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $requestBody = '{"name":"John","email":"john@example.com"}';
        add_filter('reverse_proxy_request_body', function () use ($requestBody) {
            return $requestBody;
        });

        $output = $this->whenRequesting('/api/users');

        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertNotFalse($lastRequest, 'Should have made a proxy request');
        $this->assertEquals('POST', $lastRequest->getMethod());
        $this->assertEquals('https://backend.example.com/api/users', (string) $lastRequest->getUri());
        $this->assertEquals($requestBody, (string) $lastRequest->getBody());
        $this->assertEquals('{"id":1}', $output);
    }

    public function test_it_forwards_request_headers()
    {
        $this->givenRules([new Route('/api/*', 'https://backend.example.com')]);
        $this->givenResponse(new Response(200, ['Content-Type' => 'application/json'], '{"authenticated":true}'));

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer token123';
        $_SERVER['HTTP_X_CUSTOM_HEADER'] = 'custom-value';
        $_SERVER['CONTENT_TYPE'] = 'application/json';

        $this->whenRequesting('/api/users');

        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertNotFalse($lastRequest);
        $this->assertEquals('Bearer token123', $lastRequest->getHeaderLine('Authorization'));
        $this->assertEquals('custom-value', $lastRequest->getHeaderLine('X-Custom-Header'));
        $this->assertEquals('application/json', $lastRequest->getHeaderLine('Content-Type'));
    }

    public function test_it_preserves_query_string()
    {
        $this->givenRules([new Route('/api/*', 'https://backend.example.com')]);
        $this->givenResponse(new Response(200, ['Content-Type' => 'application/json'], '{"users":[]}'));

        $this->whenRequesting('/api/users?page=2&limit=10&sort=name');

        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertNotFalse($lastRequest);
        $this->assertEquals(
            'https://backend.example.com/api/users?page=2&limit=10&sort=name',
            (string) $lastRequest->getUri()
        );
    }

    public function test_it_forwards_backend_error_status_code()
    {
        $this->givenRules([new Route('/api/*', 'https://backend.example.com')]);
        $this->givenResponse(new Response(404, ['Content-Type' => 'application/json'], '{"error":"Not Found"}'));

        $capturedResponse = null;
        add_filter('reverse_proxy_response', function ($response) use (&$capturedResponse) {
            $capturedResponse = $response;

            return $response;
        });

        $output = $this->whenRequesting('/api/users/999');

        $this->assertNotNull($capturedResponse);
        $this->assertEquals(404, $capturedResponse->getStatusCode());
        $this->assertEquals('{"error":"Not Found"}', $output);
    }

    public function test_it_forwards_backend_500_error()
    {
        $this->givenRules([new Route('/api/*', 'https://backend.example.com')]);
        $this->givenResponse(new Response(500, ['Content-Type' => 'application/json'], '{"error":"Internal Server Error"}'));

        $capturedResponse = null;
        add_filter('reverse_proxy_response', function ($response) use (&$capturedResponse) {
            $capturedResponse = $response;

            return $response;
        });

        $output = $this->whenRequesting('/api/crash');

        $this->assertNotNull($capturedResponse);
        $this->assertEquals(500, $capturedResponse->getStatusCode());
        $this->assertEquals('{"error":"Internal Server Error"}', $output);
    }

    public function test_it_handles_connection_error()
    {
        $this->givenRules([new Route('/api/*', 'https://backend.example.com')]);
        $this->mockClient->addException(
            new NetworkException('Connection refused', new Request('GET', 'https://backend.example.com/api/users'))
        );

        $output = $this->whenRequesting('/api/users');

        $this->assertStringContainsString('502', $output);
        $this->assertStringContainsString('Bad Gateway', $output);
    }

    public function test_it_forwards_response_headers()
    {
        $this->givenRules([new Route('/api/*', 'https://backend.example.com')]);
        $this->givenResponse(new Response(200, [
            'Content-Type' => 'application/json',
            'X-Custom-Header' => 'custom-value',
            'X-Request-Id' => 'abc123',
            'Cache-Control' => 'no-cache',
        ], '{"data":"test"}'));

        $capturedResponse = null;
        add_filter('reverse_proxy_response', function ($response) use (&$capturedResponse) {
            $capturedResponse = $response;

            return $response;
        });

        $output = $this->whenRequesting('/api/data');

        $this->assertNotNull($capturedResponse);
        $this->assertEquals('application/json', $capturedResponse->getHeaderLine('Content-Type'));
        $this->assertEquals('custom-value', $capturedResponse->getHeaderLine('X-Custom-Header'));
        $this->assertEquals('abc123', $capturedResponse->getHeaderLine('X-Request-Id'));
        $this->assertEquals('no-cache', $capturedResponse->getHeaderLine('Cache-Control'));
        $this->assertEquals('{"data":"test"}', $output);
    }

    public function test_it_matches_first_matching_rule()
    {
        $this->givenRules([
            new Route('/api/v2/*', 'https://api-v2.example.com'),
            new Route('/api/*', 'https://api.example.com'),
        ]);
        $this->givenResponse(new Response(200, [], '{"version":"v2"}'));

        $this->whenRequesting('/api/v2/users');

        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertNotFalse($lastRequest);
        $this->assertEquals(
            'https://api-v2.example.com/api/v2/users',
            (string) $lastRequest->getUri()
        );
    }

    public function test_it_falls_through_to_next_rule()
    {
        $this->givenRules([
            new Route('/api/v2/*', 'https://api-v2.example.com'),
            new Route('/api/*', 'https://api.example.com'),
        ]);
        $this->givenResponse(new Response(200, [], '{"version":"v1"}'));

        $this->whenRequesting('/api/users');

        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertNotFalse($lastRequest);
        $this->assertEquals(
            'https://api.example.com/api/users',
            (string) $lastRequest->getUri()
        );
    }

    public function test_it_sets_host_header_to_target_by_default()
    {
        $this->givenRules([new Route('/api/*', 'https://backend.example.com')]);
        $this->givenResponse(new Response(200, [], '{}'));

        $this->whenRequesting('/api/users');

        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertNotFalse($lastRequest);
        $this->assertEquals('backend.example.com', $lastRequest->getHeaderLine('Host'));
    }

    public function test_it_logs_proxy_request()
    {
        $this->givenRules([new Route('/api/*', 'https://backend.example.com')]);
        $this->givenResponse(new Response(200, [], '{}'));

        $logEntries = [];
        add_action('reverse_proxy_log', function ($level, $message, $context) use (&$logEntries) {
            $logEntries[] = compact('level', 'message', 'context');
        }, 10, 3);

        $this->whenRequesting('/api/users');

        $this->assertNotEmpty($logEntries);
        $requestLog = array_filter($logEntries, function ($e) {
            return strpos($e['message'], 'Proxying') !== false;
        });
        $this->assertNotEmpty($requestLog);
    }

    public function test_it_logs_proxy_error()
    {
        $this->givenRules([new Route('/api/*', 'https://backend.example.com')]);
        $this->mockClient->addException(
            new NetworkException('Connection refused', new Request('GET', 'https://backend.example.com/api/users'))
        );

        $logEntries = [];
        add_action('reverse_proxy_log', function ($level, $message, $context) use (&$logEntries) {
            $logEntries[] = compact('level', 'message', 'context');
        }, 10, 3);

        $this->whenRequesting('/api/users');

        $errorLog = array_filter($logEntries, function ($e) {
            return $e['level'] === 'error';
        });
        $this->assertNotEmpty($errorLog);
    }

    public function test_middleware_can_add_header_to_request()
    {
        $this->givenRules([
            (new Route('/api/*', 'https://backend.example.com'))
                ->middleware(function ($request, $next) {
                    return $next($request->withHeader('X-Added-By-Middleware', 'integration-test'));
                }),
        ]);
        $this->givenResponse(new Response(200, [], '{}'));

        $this->whenRequesting('/api/users');

        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertEquals('integration-test', $lastRequest->getHeaderLine('X-Added-By-Middleware'));
    }

    public function test_middleware_can_modify_response()
    {
        $this->givenRules([
            (new Route('/api/*', 'https://backend.example.com'))
                ->middleware(function ($request, $next) {
                    $response = $next($request);

                    return $response->withHeader('X-Processed', 'true');
                }),
        ]);
        $this->givenResponse(new Response(200, [], '{"data":"test"}'));

        $capturedResponse = null;
        add_filter('reverse_proxy_response', function ($response) use (&$capturedResponse) {
            $capturedResponse = $response;

            return $response;
        });

        $this->whenRequesting('/api/users');

        $this->assertEquals('true', $capturedResponse->getHeaderLine('X-Processed'));
    }

    public function test_set_host_middleware_sets_custom_host()
    {
        $this->givenRules([
            new Route('/api/*', 'https://backend.example.com', [
                new SetHostMiddleware('custom-api.example.com'),
            ]),
        ]);
        $this->givenResponse(new Response(200, [], '{}'));

        $this->whenRequesting('/api/users');

        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertNotFalse($lastRequest);
        $this->assertEquals('custom-api.example.com', $lastRequest->getHeaderLine('Host'));
    }

    public function test_proxy_headers_middleware_adds_forwarded_headers()
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';
        $this->givenRules([
            new Route('/api/*', 'https://backend.example.com', [
                new ProxyHeadersMiddleware(),
            ]),
        ]);
        $this->givenResponse(new Response(200, [], '{}'));

        $this->whenRequesting('/api/users');

        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertNotFalse($lastRequest);
        $this->assertEquals('192.168.1.100', $lastRequest->getHeaderLine('X-Real-IP'));
        $this->assertNotEmpty($lastRequest->getHeaderLine('X-Forwarded-For'));
        $this->assertNotEmpty($lastRequest->getHeaderLine('X-Forwarded-Proto'));
        $this->assertNotEmpty($lastRequest->getHeaderLine('X-Forwarded-Port'));
    }

    public function test_rewrite_path_middleware_rewrites_path()
    {
        $this->givenRules([
            new Route('/api/v1/*', 'https://backend.example.com', [
                new RewritePathMiddleware('/api/v1/*', '/v1/$1'),
            ]),
        ]);
        $this->givenResponse(new Response(200, [], '{}'));

        $this->whenRequesting('/api/v1/users/123');

        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertNotFalse($lastRequest);
        $this->assertEquals(
            'https://backend.example.com/v1/users/123',
            (string) $lastRequest->getUri()
        );
    }

    public function test_middlewares_can_be_combined()
    {
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        $this->givenRules([
            new Route('/api/v1/*', 'https://127.0.0.1:8080', [
                new RewritePathMiddleware('/api/v1/*', '/v1/$1'),
                new ProxyHeadersMiddleware(),
                new SetHostMiddleware('api.example.com'),
            ]),
        ]);
        $this->givenResponse(new Response(200, [], '{"success":true}'));

        $output = $this->whenRequesting('/api/v1/users');

        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertNotFalse($lastRequest);
        $this->assertEquals('https://127.0.0.1:8080/v1/users', (string) $lastRequest->getUri());
        $this->assertEquals('api.example.com', $lastRequest->getHeaderLine('Host'));
        $this->assertEquals('10.0.0.1', $lastRequest->getHeaderLine('X-Real-IP'));
        $this->assertEquals('{"success":true}', $output);
    }

    public function test_route_accepts_middlewares_in_constructor()
    {
        $this->givenRules([
            new Route('/api/*', 'https://backend.example.com', [
                new SetHostMiddleware('test.example.com'),
            ]),
        ]);
        $this->givenResponse(new Response(200, [], '{}'));

        $this->whenRequesting('/api/users');

        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertEquals('test.example.com', $lastRequest->getHeaderLine('Host'));
    }

    public function test_allow_methods_middleware_allows_configured_methods()
    {
        $this->givenRules([
            new Route('/api/*', 'https://backend.example.com', [
                new AllowMethodsMiddleware(['GET', 'POST']),
            ]),
        ]);
        $this->givenResponse(new Response(200, [], '{"success":true}'));

        $output = $this->whenRequesting('/api/users');

        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertNotFalse($lastRequest);
        $this->assertEquals('{"success":true}', $output);
    }

    public function test_allow_methods_middleware_returns_405_for_disallowed_methods()
    {
        $this->givenRules([
            new Route('/api/*', 'https://backend.example.com', [
                new AllowMethodsMiddleware(['GET']),
            ]),
        ]);

        $_SERVER['REQUEST_METHOD'] = 'DELETE';

        $capturedResponse = null;
        add_filter('reverse_proxy_response', function ($response) use (&$capturedResponse) {
            $capturedResponse = $response;

            return $response;
        });

        $output = $this->whenRequesting('/api/users');

        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertFalse($lastRequest, 'Should not have made a proxy request');
        $this->assertNotNull($capturedResponse);
        $this->assertEquals(405, $capturedResponse->getStatusCode());
        $this->assertEquals('GET', $capturedResponse->getHeaderLine('Allow'));
    }

    public function test_route_matches_specific_http_method()
    {
        $this->givenRules([
            new Route('POST /api/users', 'https://backend.example.com'),
        ]);
        $this->givenResponse(new Response(201, [], '{"id":1}'));

        $_SERVER['REQUEST_METHOD'] = 'POST';

        $output = $this->whenRequesting('/api/users');

        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertNotFalse($lastRequest);
        $this->assertEquals('POST', $lastRequest->getMethod());
        $this->assertEquals('{"id":1}', $output);
    }

    public function test_route_does_not_match_when_method_differs()
    {
        $this->givenRules([
            new Route('POST /api/users', 'https://backend.example.com'),
        ]);

        $_SERVER['REQUEST_METHOD'] = 'GET';

        $this->go_to('/api/users');

        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertFalse($lastRequest, 'Should not proxy when method does not match');
    }

    public function test_route_matches_multiple_methods_with_pipe()
    {
        $this->givenRules([
            new Route('GET|POST /api/users', 'https://backend.example.com'),
        ]);
        $this->givenResponse(new Response(200, [], '{"users":[]}'));

        $output = $this->whenRequesting('/api/users');

        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertNotFalse($lastRequest);
        $this->assertEquals('{"users":[]}', $output);
    }
}
