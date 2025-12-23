<?php

namespace ReverseProxy\Tests\Unit;

use Http\Mock\Client as MockClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use ReverseProxy\MiddlewareInterface;
use ReverseProxy\ReverseProxy;
use ReverseProxy\Rule;

class ReverseProxyTest extends TestCase
{
    /** @var MockClient */
    private $mockClient;

    /** @var Psr17Factory */
    private $psr17Factory;

    /** @var ReverseProxy */
    private $reverseProxy;

    protected function setUp(): void
    {
        $this->mockClient = new MockClient();
        $this->psr17Factory = new Psr17Factory();
        $this->reverseProxy = new ReverseProxy(
            $this->mockClient,
            $this->psr17Factory,
            $this->psr17Factory
        );
    }

    public function test_it_returns_null_when_no_rules_match()
    {
        $request = new ServerRequest('GET', '/about');
        $rules = [
            new Rule('/api/*', 'https://backend.example.com'),
        ];

        $response = $this->reverseProxy->handle($request, $rules);

        $this->assertNull($response);
    }

    public function test_it_proxies_matching_request()
    {
        $this->mockClient->addResponse(
            new Response(200, ['Content-Type' => 'application/json'], '{"message":"hello"}')
        );

        $request = new ServerRequest('GET', '/api/users');
        $rules = [
            new Rule('/api/*', 'https://backend.example.com'),
        ];

        $response = $this->reverseProxy->handle($request, $rules);

        $this->assertNotNull($response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('{"message":"hello"}', (string) $response->getBody());

        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertEquals('https://backend.example.com/api/users', (string) $lastRequest->getUri());
    }

    public function test_it_forwards_post_request_with_body()
    {
        $this->mockClient->addResponse(new Response(201, [], '{"id":1}'));

        $body = '{"name":"John"}';
        $request = (new ServerRequest('POST', '/api/users'))
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psr17Factory->createStream($body));

        $rules = [
            new Rule('/api/*', 'https://backend.example.com'),
        ];

        $response = $this->reverseProxy->handle($request, $rules);

        $this->assertEquals(201, $response->getStatusCode());

        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertEquals('POST', $lastRequest->getMethod());
        $this->assertEquals($body, (string) $lastRequest->getBody());
    }

    public function test_it_forwards_request_headers()
    {
        $this->mockClient->addResponse(new Response(200, [], '{}'));

        $request = (new ServerRequest('GET', '/api/users'))
            ->withHeader('Authorization', 'Bearer token123')
            ->withHeader('X-Custom-Header', 'custom-value');

        $rules = [
            new Rule('/api/*', 'https://backend.example.com'),
        ];

        $this->reverseProxy->handle($request, $rules);

        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertEquals('Bearer token123', $lastRequest->getHeaderLine('Authorization'));
        $this->assertEquals('custom-value', $lastRequest->getHeaderLine('X-Custom-Header'));
    }

    public function test_it_preserves_query_string()
    {
        $this->mockClient->addResponse(new Response(200, [], '{}'));

        $request = new ServerRequest('GET', '/api/users?page=2&limit=10');
        $rules = [
            new Rule('/api/*', 'https://backend.example.com'),
        ];

        $this->reverseProxy->handle($request, $rules);

        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertEquals(
            'https://backend.example.com/api/users?page=2&limit=10',
            (string) $lastRequest->getUri()
        );
    }

    public function test_it_rewrites_path()
    {
        $this->mockClient->addResponse(new Response(200, [], '{}'));

        $request = new ServerRequest('GET', '/api/v1/users/123');
        $rules = [
            new Rule('/api/v1/*', 'https://backend.example.com', '/v1/$1'),
        ];

        $this->reverseProxy->handle($request, $rules);

        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertEquals(
            'https://backend.example.com/v1/users/123',
            (string) $lastRequest->getUri()
        );
    }

    public function test_it_sets_host_header_to_target_by_default()
    {
        $this->mockClient->addResponse(new Response(200, [], '{}'));

        $request = (new ServerRequest('GET', '/api/users'))
            ->withHeader('Host', 'original.example.com');
        $rules = [
            new Rule('/api/*', 'https://backend.example.com'),
        ];

        $this->reverseProxy->handle($request, $rules);

        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertEquals('backend.example.com', $lastRequest->getHeaderLine('Host'));
    }

    public function test_it_preserves_original_host_when_configured()
    {
        $this->mockClient->addResponse(new Response(200, [], '{}'));

        $request = (new ServerRequest('GET', '/api/users'))
            ->withHeader('Host', 'original.example.com');
        $rules = [
            new Rule('/api/*', 'https://backend.example.com', null, true),
        ];

        $this->reverseProxy->handle($request, $rules);

        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertEquals('original.example.com', $lastRequest->getHeaderLine('Host'));
    }

    public function test_it_matches_first_rule()
    {
        $this->mockClient->addResponse(new Response(200, [], '{}'));

        $request = new ServerRequest('GET', '/api/v2/users');
        $rules = [
            new Rule('/api/v2/*', 'https://api-v2.example.com'),
            new Rule('/api/*', 'https://api.example.com'),
        ];

        $this->reverseProxy->handle($request, $rules);

        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertEquals(
            'https://api-v2.example.com/api/v2/users',
            (string) $lastRequest->getUri()
        );
    }

    public function test_middleware_can_modify_request()
    {
        $this->mockClient->addResponse(new Response(200, [], '{}'));

        $request = new ServerRequest('GET', '/api/users');
        $rule = (new Rule('/api/*', 'https://backend.example.com'))
            ->middleware(function ($request, $next) {
                return $next($request->withHeader('X-Added-By-Middleware', 'yes'));
            });

        $this->reverseProxy->handle($request, [$rule]);

        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertEquals('yes', $lastRequest->getHeaderLine('X-Added-By-Middleware'));
    }

    public function test_middleware_can_modify_response()
    {
        $this->mockClient->addResponse(new Response(200, [], '{"original":true}'));

        $request = new ServerRequest('GET', '/api/users');
        $rule = (new Rule('/api/*', 'https://backend.example.com'))
            ->middleware(function ($request, $next) {
                $response = $next($request);
                return $response->withHeader('X-Modified-By-Middleware', 'yes');
            });

        $response = $this->reverseProxy->handle($request, [$rule]);

        $this->assertEquals('yes', $response->getHeaderLine('X-Modified-By-Middleware'));
    }

    public function test_middleware_can_short_circuit()
    {
        // No response added to mock client - it should not be called
        $request = new ServerRequest('GET', '/api/users');
        $rule = (new Rule('/api/*', 'https://backend.example.com'))
            ->middleware(function ($request, $next) {
                // Return early without calling $next
                return new Response(403, [], '{"error":"forbidden"}');
            });

        $response = $this->reverseProxy->handle($request, [$rule]);

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertEquals('{"error":"forbidden"}', (string) $response->getBody());
        $this->assertFalse($this->mockClient->getLastRequest());
    }

    public function test_multiple_middlewares_execute_in_order()
    {
        $this->mockClient->addResponse(new Response(200, [], '{}'));

        $order = [];
        $request = new ServerRequest('GET', '/api/users');
        $rule = (new Rule('/api/*', 'https://backend.example.com'))
            ->middleware(function ($request, $next) use (&$order) {
                $order[] = 'middleware1:before';
                $response = $next($request);
                $order[] = 'middleware1:after';
                return $response;
            })
            ->middleware(function ($request, $next) use (&$order) {
                $order[] = 'middleware2:before';
                $response = $next($request);
                $order[] = 'middleware2:after';
                return $response;
            });

        $this->reverseProxy->handle($request, [$rule]);

        $this->assertEquals([
            'middleware1:before',
            'middleware2:before',
            'middleware2:after',
            'middleware1:after',
        ], $order);
    }

    public function test_middleware_interface_works()
    {
        $this->mockClient->addResponse(new Response(200, [], '{}'));

        $middleware = new class implements MiddlewareInterface {
            public function process(RequestInterface $request, callable $next): ResponseInterface
            {
                $response = $next($request->withHeader('X-From-Interface', 'yes'));
                return $response->withHeader('X-Processed-By-Interface', 'yes');
            }
        };

        $request = new ServerRequest('GET', '/api/users');
        $rule = (new Rule('/api/*', 'https://backend.example.com'))
            ->middleware($middleware);

        $response = $this->reverseProxy->handle($request, [$rule]);

        // Verify request was modified
        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertEquals('yes', $lastRequest->getHeaderLine('X-From-Interface'));

        // Verify response was modified
        $this->assertEquals('yes', $response->getHeaderLine('X-Processed-By-Interface'));
    }
}
