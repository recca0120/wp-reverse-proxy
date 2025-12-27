<?php

namespace ReverseProxy\Tests\Unit\Middleware;

use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use ReverseProxy\Middleware\Cors;

class CorsTest extends TestCase
{
    public function test_it_passes_through_without_origin_header()
    {
        $middleware = new Cors(['https://example.com']);
        $request = new ServerRequest('GET', 'https://api.example.com/users');

        $called = false;
        $response = $middleware->process($request, function ($req) use (&$called) {
            $called = true;

            return new Response(200, [], '{"data":"test"}');
        });

        $this->assertTrue($called);
        $this->assertFalse($response->hasHeader('Access-Control-Allow-Origin'));
    }

    public function test_it_adds_cors_headers_for_allowed_origin()
    {
        $middleware = new Cors(['https://example.com']);
        $request = (new ServerRequest('GET', 'https://api.example.com/users'))
            ->withHeader('Origin', 'https://example.com');

        $response = $middleware->process($request, function ($req) {
            return new Response(200);
        });

        $this->assertEquals('https://example.com', $response->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertEquals('Origin', $response->getHeaderLine('Vary'));
    }

    public function test_it_allows_wildcard_origin()
    {
        $middleware = new Cors(['*']);
        $request = (new ServerRequest('GET', 'https://api.example.com/users'))
            ->withHeader('Origin', 'https://any-site.com');

        $response = $middleware->process($request, function ($req) {
            return new Response(200);
        });

        $this->assertEquals('*', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    public function test_it_does_not_add_headers_for_disallowed_origin()
    {
        $middleware = new Cors(['https://allowed.com']);
        $request = (new ServerRequest('GET', 'https://api.example.com/users'))
            ->withHeader('Origin', 'https://disallowed.com');

        $response = $middleware->process($request, function ($req) {
            return new Response(200);
        });

        $this->assertFalse($response->hasHeader('Access-Control-Allow-Origin'));
    }

    public function test_it_handles_preflight_request()
    {
        $middleware = new Cors(['https://example.com']);
        $request = (new ServerRequest('OPTIONS', 'https://api.example.com/users'))
            ->withHeader('Origin', 'https://example.com')
            ->withHeader('Access-Control-Request-Method', 'POST');

        $called = false;
        $response = $middleware->process($request, function ($req) use (&$called) {
            $called = true;

            return new Response(200);
        });

        $this->assertFalse($called);
        $this->assertEquals(204, $response->getStatusCode());
        $this->assertEquals('https://example.com', $response->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertNotEmpty($response->getHeaderLine('Access-Control-Allow-Methods'));
        $this->assertNotEmpty($response->getHeaderLine('Access-Control-Allow-Headers'));
    }

    public function test_it_includes_credentials_header_when_enabled()
    {
        $middleware = new Cors(['https://example.com'], ['GET'], ['Content-Type'], true);
        $request = (new ServerRequest('GET', 'https://api.example.com/users'))
            ->withHeader('Origin', 'https://example.com');

        $response = $middleware->process($request, function ($req) {
            return new Response(200);
        });

        $this->assertEquals('true', $response->getHeaderLine('Access-Control-Allow-Credentials'));
    }

    public function test_it_includes_max_age_in_preflight_response()
    {
        $middleware = new Cors(['https://example.com'], ['GET'], ['Content-Type'], false, 86400);
        $request = (new ServerRequest('OPTIONS', 'https://api.example.com/users'))
            ->withHeader('Origin', 'https://example.com')
            ->withHeader('Access-Control-Request-Method', 'GET');

        $response = $middleware->process($request, function ($req) {
            return new Response(200);
        });

        $this->assertEquals('86400', $response->getHeaderLine('Access-Control-Max-Age'));
    }

    public function test_it_uses_custom_allowed_methods()
    {
        $middleware = new Cors(['https://example.com'], ['GET', 'POST']);
        $request = (new ServerRequest('OPTIONS', 'https://api.example.com/users'))
            ->withHeader('Origin', 'https://example.com')
            ->withHeader('Access-Control-Request-Method', 'POST');

        $response = $middleware->process($request, function ($req) {
            return new Response(200);
        });

        $this->assertEquals('GET, POST', $response->getHeaderLine('Access-Control-Allow-Methods'));
    }

    public function test_it_uses_custom_allowed_headers()
    {
        $middleware = new Cors(['https://example.com'], ['GET'], ['X-Custom-Header', 'Authorization']);
        $request = (new ServerRequest('OPTIONS', 'https://api.example.com/users'))
            ->withHeader('Origin', 'https://example.com')
            ->withHeader('Access-Control-Request-Method', 'GET');

        $response = $middleware->process($request, function ($req) {
            return new Response(200);
        });

        $this->assertEquals('X-Custom-Header, Authorization', $response->getHeaderLine('Access-Control-Allow-Headers'));
    }

    public function test_options_without_access_control_request_method_is_not_preflight()
    {
        $middleware = new Cors(['https://example.com']);
        $request = (new ServerRequest('OPTIONS', 'https://api.example.com/users'))
            ->withHeader('Origin', 'https://example.com');

        $called = false;
        $response = $middleware->process($request, function ($req) use (&$called) {
            $called = true;

            return new Response(200);
        });

        $this->assertTrue($called);
    }
}
