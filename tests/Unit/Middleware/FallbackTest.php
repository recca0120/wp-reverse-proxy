<?php

namespace Recca0120\ReverseProxy\Tests\Unit\Middleware;

use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Recca0120\ReverseProxy\Exceptions\FallbackException;
use Recca0120\ReverseProxy\Middleware\Fallback;

class FallbackTest extends TestCase
{
    public function test_it_passes_through_on_success()
    {
        $middleware = new Fallback([404]);
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $response = $middleware->process($request, function ($req) {
            return new Response(200, [], '{"data":"test"}');
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_it_throws_fallback_exception_on_404()
    {
        $middleware = new Fallback([404]);
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $this->expectException(FallbackException::class);

        $middleware->process($request, function ($req) {
            return new Response(404);
        });
    }

    public function test_it_throws_on_multiple_status_codes()
    {
        $middleware = new Fallback([404, 410]);
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $this->expectException(FallbackException::class);

        $middleware->process($request, function ($req) {
            return new Response(410);
        });
    }

    public function test_it_does_not_throw_on_non_matching_status()
    {
        $middleware = new Fallback([404]);
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $response = $middleware->process($request, function ($req) {
            return new Response(500);
        });

        $this->assertEquals(500, $response->getStatusCode());
    }

    public function test_it_defaults_to_404_only()
    {
        $middleware = new Fallback;
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $this->expectException(FallbackException::class);

        $middleware->process($request, function ($req) {
            return new Response(404);
        });
    }

    public function test_default_does_not_throw_on_410()
    {
        $middleware = new Fallback;
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $response = $middleware->process($request, function ($req) {
            return new Response(410);
        });

        $this->assertEquals(410, $response->getStatusCode());
    }

    public function test_it_has_high_priority()
    {
        $middleware = new Fallback;

        $this->assertEquals(100, $middleware->priority);
    }
}
