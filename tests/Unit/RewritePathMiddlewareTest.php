<?php

namespace ReverseProxy\Tests\Unit;

use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use ReverseProxy\Middleware\RewritePathMiddleware;

class RewritePathMiddlewareTest extends TestCase
{
    public function test_it_rewrites_path_with_wildcard_capture()
    {
        $middleware = new RewritePathMiddleware('/api/v1/*', '/v1/$1');
        $request = new ServerRequest('GET', 'https://backend.example.com/api/v1/users/123');

        $middleware->process($request, function ($req) {
            $this->assertEquals('/v1/users/123', $req->getUri()->getPath());

            return new Response(200);
        });
    }

    public function test_it_rewrites_path_removing_prefix()
    {
        $middleware = new RewritePathMiddleware('/api/*', '/$1');
        $request = new ServerRequest('GET', 'https://backend.example.com/api/users');

        $middleware->process($request, function ($req) {
            $this->assertEquals('/users', $req->getUri()->getPath());

            return new Response(200);
        });
    }

    public function test_it_rewrites_path_adding_prefix()
    {
        $middleware = new RewritePathMiddleware('/users/*', '/api/v2/users/$1');
        $request = new ServerRequest('GET', 'https://backend.example.com/users/123');

        $middleware->process($request, function ($req) {
            $this->assertEquals('/api/v2/users/123', $req->getUri()->getPath());

            return new Response(200);
        });
    }

    public function test_it_preserves_query_string()
    {
        $middleware = new RewritePathMiddleware('/api/v1/*', '/v1/$1');
        $request = new ServerRequest('GET', 'https://backend.example.com/api/v1/users?page=2&limit=10');

        $middleware->process($request, function ($req) {
            $this->assertEquals('/v1/users', $req->getUri()->getPath());
            $this->assertEquals('page=2&limit=10', $req->getUri()->getQuery());

            return new Response(200);
        });
    }

    public function test_it_does_not_modify_path_when_pattern_does_not_match()
    {
        $middleware = new RewritePathMiddleware('/api/v1/*', '/v1/$1');
        $request = new ServerRequest('GET', 'https://backend.example.com/other/path');

        $middleware->process($request, function ($req) {
            $this->assertEquals('/other/path', $req->getUri()->getPath());

            return new Response(200);
        });
    }

    public function test_it_handles_exact_path_match()
    {
        $middleware = new RewritePathMiddleware('/old-endpoint', '/new-endpoint');
        $request = new ServerRequest('GET', 'https://backend.example.com/old-endpoint');

        $middleware->process($request, function ($req) {
            $this->assertEquals('/new-endpoint', $req->getUri()->getPath());

            return new Response(200);
        });
    }

    public function test_it_handles_multiple_wildcards()
    {
        $middleware = new RewritePathMiddleware('/api/*/resources/*', '/v2/$1/items/$2');
        $request = new ServerRequest('GET', 'https://backend.example.com/api/users/resources/123');

        $middleware->process($request, function ($req) {
            $this->assertEquals('/v2/users/items/123', $req->getUri()->getPath());

            return new Response(200);
        });
    }
}
