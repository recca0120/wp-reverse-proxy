<?php

namespace ReverseProxy\Tests\Unit\Middleware;

use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use ReverseProxy\Middleware\ProxyHeaders;

class ProxyHeadersTest extends TestCase
{
    public function test_it_sets_x_real_ip_header(): void
    {
        $middleware = new ProxyHeaders('192.168.1.100');
        $request = new ServerRequest('GET', 'https://target.example.com/api/users');

        $middleware->process($request, function ($req) {
            $this->assertEquals('192.168.1.100', $req->getHeaderLine('X-Real-IP'));

            return new Response(200);
        });
    }

    public function test_it_sets_x_forwarded_for_header(): void
    {
        $middleware = new ProxyHeaders('192.168.1.100');
        $request = new ServerRequest('GET', 'https://target.example.com/api/users');

        $middleware->process($request, function ($req) {
            $this->assertEquals('192.168.1.100', $req->getHeaderLine('X-Forwarded-For'));

            return new Response(200);
        });
    }

    public function test_it_appends_to_existing_x_forwarded_for_header(): void
    {
        $middleware = new ProxyHeaders('192.168.1.100');
        $request = (new ServerRequest('GET', 'https://target.example.com/api/users'))
            ->withHeader('X-Forwarded-For', '10.0.0.1, 10.0.0.2');

        $middleware->process($request, function ($req) {
            $this->assertEquals('10.0.0.1, 10.0.0.2, 192.168.1.100', $req->getHeaderLine('X-Forwarded-For'));

            return new Response(200);
        });
    }

    public function test_it_sets_x_forwarded_host(): void
    {
        $middleware = new ProxyHeaders(null, 'my-wordpress.com');
        $request = new ServerRequest('GET', 'https://target.example.com/api/users');

        $middleware->process($request, function ($req) {
            $this->assertEquals('my-wordpress.com', $req->getHeaderLine('X-Forwarded-Host'));

            return new Response(200);
        });
    }

    public function test_it_sets_x_forwarded_proto_https(): void
    {
        $middleware = new ProxyHeaders(null, null, 'https');
        $request = new ServerRequest('GET', 'https://target.example.com/api/users');

        $middleware->process($request, function ($req) {
            $this->assertEquals('https', $req->getHeaderLine('X-Forwarded-Proto'));

            return new Response(200);
        });
    }

    public function test_it_sets_x_forwarded_proto_http(): void
    {
        $middleware = new ProxyHeaders(null, null, 'http');
        $request = new ServerRequest('GET', 'https://target.example.com/api/users');

        $middleware->process($request, function ($req) {
            $this->assertEquals('http', $req->getHeaderLine('X-Forwarded-Proto'));

            return new Response(200);
        });
    }

    public function test_it_sets_x_forwarded_port(): void
    {
        $middleware = new ProxyHeaders(null, null, null, '8443');
        $request = new ServerRequest('GET', 'https://target.example.com/api/users');

        $middleware->process($request, function ($req) {
            $this->assertEquals('8443', $req->getHeaderLine('X-Forwarded-Port'));

            return new Response(200);
        });
    }

    public function test_it_sets_forwarded_header(): void
    {
        $middleware = new ProxyHeaders('192.168.1.100', 'my-wordpress.com', 'https');
        $request = new ServerRequest('GET', 'https://target.example.com/api/users');

        $middleware->process($request, function ($req) {
            $this->assertEquals(
                'for=192.168.1.100;host=my-wordpress.com;proto=https',
                $req->getHeaderLine('Forwarded')
            );

            return new Response(200);
        });
    }

    public function test_it_quotes_ipv6_addresses_in_forwarded_header(): void
    {
        $middleware = new ProxyHeaders('2001:db8::1', 'my-wordpress.com', 'https');
        $request = new ServerRequest('GET', 'https://target.example.com/api/users');

        $middleware->process($request, function ($req) {
            $this->assertEquals(
                'for="[2001:db8::1]";host=my-wordpress.com;proto=https',
                $req->getHeaderLine('Forwarded')
            );

            return new Response(200);
        });
    }

    public function test_it_appends_to_existing_forwarded_header(): void
    {
        $middleware = new ProxyHeaders('192.168.1.100', 'my-wordpress.com', 'https');
        $request = (new ServerRequest('GET', 'https://target.example.com/api/users'))
            ->withHeader('Forwarded', 'for=10.0.0.1;host=previous.com;proto=http');

        $middleware->process($request, function ($req) {
            $this->assertEquals(
                'for=10.0.0.1;host=previous.com;proto=http, for=192.168.1.100;host=my-wordpress.com;proto=https',
                $req->getHeaderLine('Forwarded')
            );

            return new Response(200);
        });
    }

    public function test_it_sets_all_proxy_headers(): void
    {
        $middleware = new ProxyHeaders('192.168.1.100', 'my-wordpress.com', 'https', '443');
        $request = new ServerRequest('GET', 'https://target.example.com/api/users');

        $middleware->process($request, function ($req) {
            $this->assertEquals('192.168.1.100', $req->getHeaderLine('X-Real-IP'));
            $this->assertEquals('192.168.1.100', $req->getHeaderLine('X-Forwarded-For'));
            $this->assertEquals('my-wordpress.com', $req->getHeaderLine('X-Forwarded-Host'));
            $this->assertEquals('https', $req->getHeaderLine('X-Forwarded-Proto'));
            $this->assertEquals('443', $req->getHeaderLine('X-Forwarded-Port'));
            $this->assertEquals(
                'for=192.168.1.100;host=my-wordpress.com;proto=https',
                $req->getHeaderLine('Forwarded')
            );

            return new Response(200);
        });
    }

    public function test_it_uses_server_defaults_when_no_args_provided(): void
    {
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        $_SERVER['HTTP_HOST'] = 'default-host.com';
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['SERVER_PORT'] = '443';

        $middleware = new ProxyHeaders();
        $request = new ServerRequest('GET', 'https://target.example.com/api/users');

        $middleware->process($request, function ($req) {
            $this->assertEquals('10.0.0.1', $req->getHeaderLine('X-Real-IP'));
            $this->assertEquals('default-host.com', $req->getHeaderLine('X-Forwarded-Host'));
            $this->assertEquals('https', $req->getHeaderLine('X-Forwarded-Proto'));
            $this->assertEquals('443', $req->getHeaderLine('X-Forwarded-Port'));

            return new Response(200);
        });

        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_HOST'], $_SERVER['HTTPS'], $_SERVER['SERVER_PORT']);
    }
}
