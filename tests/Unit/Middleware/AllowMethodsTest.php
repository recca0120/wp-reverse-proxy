<?php

namespace Recca0120\ReverseProxy\Tests\Unit\Middleware;

use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Recca0120\ReverseProxy\Middleware\AllowMethods;

class AllowMethodsTest extends TestCase
{
    public function test_it_allows_request_when_method_is_in_allowed_list()
    {
        $middleware = new AllowMethods(['GET', 'POST']);
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $called = false;
        $response = $middleware->process($request, function ($req) use (&$called) {
            $called = true;

            return new Response(200, [], '{"success":true}');
        });

        $this->assertTrue($called);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_it_allows_post_request()
    {
        $middleware = new AllowMethods(['GET', 'POST']);
        $request = new ServerRequest('POST', 'https://example.com/api/users');

        $called = false;
        $response = $middleware->process($request, function ($req) use (&$called) {
            $called = true;

            return new Response(201);
        });

        $this->assertTrue($called);
        $this->assertEquals(201, $response->getStatusCode());
    }

    public function test_it_returns_405_when_method_not_allowed()
    {
        $middleware = new AllowMethods(['GET']);
        $request = new ServerRequest('POST', 'https://example.com/api/users');

        $called = false;
        $response = $middleware->process($request, function ($req) use (&$called) {
            $called = true;

            return new Response(200);
        });

        $this->assertFalse($called);
        $this->assertEquals(405, $response->getStatusCode());
    }

    public function test_it_includes_allow_header_in_405_response()
    {
        $middleware = new AllowMethods(['GET', 'POST', 'PUT']);
        $request = new ServerRequest('DELETE', 'https://example.com/api/users');

        $response = $middleware->process($request, function ($req) {
            return new Response(200);
        });

        $this->assertEquals(405, $response->getStatusCode());
        $this->assertEquals('GET, POST, PUT', $response->getHeaderLine('Allow'));
    }

    public function test_it_handles_case_insensitive_methods()
    {
        $middleware = new AllowMethods(['get', 'post']);
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $called = false;
        $response = $middleware->process($request, function ($req) use (&$called) {
            $called = true;

            return new Response(200);
        });

        $this->assertTrue($called);
    }

    public function test_it_allows_options_request_for_cors_preflight()
    {
        $middleware = new AllowMethods(['GET', 'POST']);
        $request = new ServerRequest('OPTIONS', 'https://example.com/api/users');

        $response = $middleware->process($request, function ($req) {
            return new Response(200);
        });

        // OPTIONS should be allowed for CORS preflight
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_it_returns_json_error_body()
    {
        $middleware = new AllowMethods(['GET']);
        $request = new ServerRequest('POST', 'https://example.com/api/users');

        $response = $middleware->process($request, function ($req) {
            return new Response(200);
        });

        $body = json_decode((string) $response->getBody(), true);
        $this->assertEquals(405, $body['status']);
        $this->assertStringContainsString('Method Not Allowed', $body['error']);
    }
}
