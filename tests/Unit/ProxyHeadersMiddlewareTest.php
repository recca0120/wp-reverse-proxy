<?php

namespace ReverseProxy\Tests\Unit;

use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use ReverseProxy\Middleware\ProxyHeadersMiddleware;

class ProxyHeadersMiddlewareTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['REMOTE_ADDR']);
    }

    public function test_it_sets_x_real_ip_header()
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';
        $middleware = new ProxyHeadersMiddleware;
        $request = new ServerRequest('GET', 'https://localhost/api/users');

        $middleware->process($request, function ($req) {
            $this->assertEquals('192.168.1.100', $req->getHeaderLine('X-Real-IP'));

            return new Response(200);
        });
    }

    public function test_it_sets_x_forwarded_for_header()
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';
        $middleware = new ProxyHeadersMiddleware;
        $request = new ServerRequest('GET', 'https://localhost/api/users');

        $middleware->process($request, function ($req) {
            $this->assertEquals('192.168.1.100', $req->getHeaderLine('X-Forwarded-For'));

            return new Response(200);
        });
    }

    public function test_it_appends_to_existing_x_forwarded_for_header()
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';
        $middleware = new ProxyHeadersMiddleware;
        $request = (new ServerRequest('GET', 'https://localhost/api/users'))
            ->withHeader('X-Forwarded-For', '10.0.0.1, 10.0.0.2');

        $middleware->process($request, function ($req) {
            $this->assertEquals('10.0.0.1, 10.0.0.2, 192.168.1.100', $req->getHeaderLine('X-Forwarded-For'));

            return new Response(200);
        });
    }

    public function test_it_sets_x_forwarded_proto_from_request_scheme()
    {
        $middleware = new ProxyHeadersMiddleware;
        $request = new ServerRequest('GET', 'https://localhost/api/users');

        $middleware->process($request, function ($req) {
            $this->assertEquals('https', $req->getHeaderLine('X-Forwarded-Proto'));

            return new Response(200);
        });
    }

    public function test_it_sets_x_forwarded_proto_as_http()
    {
        $middleware = new ProxyHeadersMiddleware;
        $request = new ServerRequest('GET', 'http://localhost/api/users');

        $middleware->process($request, function ($req) {
            $this->assertEquals('http', $req->getHeaderLine('X-Forwarded-Proto'));

            return new Response(200);
        });
    }

    public function test_it_sets_x_forwarded_port_from_request_uri()
    {
        $middleware = new ProxyHeadersMiddleware;
        $request = new ServerRequest('GET', 'https://localhost:8443/api/users');

        $middleware->process($request, function ($req) {
            $this->assertEquals('8443', $req->getHeaderLine('X-Forwarded-Port'));

            return new Response(200);
        });
    }

    public function test_it_sets_default_port_for_https()
    {
        $middleware = new ProxyHeadersMiddleware;
        $request = new ServerRequest('GET', 'https://localhost/api/users');

        $middleware->process($request, function ($req) {
            $this->assertEquals('443', $req->getHeaderLine('X-Forwarded-Port'));

            return new Response(200);
        });
    }

    public function test_it_sets_default_port_for_http()
    {
        $middleware = new ProxyHeadersMiddleware;
        $request = new ServerRequest('GET', 'http://localhost/api/users');

        $middleware->process($request, function ($req) {
            $this->assertEquals('80', $req->getHeaderLine('X-Forwarded-Port'));

            return new Response(200);
        });
    }

    public function test_it_handles_missing_remote_addr()
    {
        unset($_SERVER['REMOTE_ADDR']);
        $middleware = new ProxyHeadersMiddleware;
        $request = new ServerRequest('GET', 'https://localhost/api/users');

        $middleware->process($request, function ($req) {
            $this->assertEquals('', $req->getHeaderLine('X-Real-IP'));
            $this->assertEquals('', $req->getHeaderLine('X-Forwarded-For'));

            return new Response(200);
        });
    }
}
