<?php

namespace Recca0120\ReverseProxy\Tests\Unit\Middleware;

use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Recca0120\ReverseProxy\Middleware\RewritePath;
use Recca0120\ReverseProxy\Routing\Route;

class RewritePathTest extends TestCase
{
    public function test_it_rewrites_path_with_single_capture()
    {
        $route = (new Route('/api/v1/*', 'https://backend.example.com'))
            ->middleware(new RewritePath('/v1/$1'));

        // Trigger route matching to populate captures
        $route->matches(new ServerRequest('GET', 'https://example.com/api/v1/users/123'));

        $middlewares = $route->getMiddlewares();
        $request = new ServerRequest('GET', 'https://backend.example.com/api/v1/users/123');

        $middlewares[0]->process($request, function ($req) {
            $this->assertEquals('/v1/users/123', $req->getUri()->getPath());

            return new Response(200);
        });
    }

    public function test_it_rewrites_path_removing_prefix()
    {
        $route = (new Route('/api/*', 'https://backend.example.com'))
            ->middleware(new RewritePath('/$1'));

        $route->matches(new ServerRequest('GET', 'https://example.com/api/users'));

        $middlewares = $route->getMiddlewares();
        $request = new ServerRequest('GET', 'https://backend.example.com/api/users');

        $middlewares[0]->process($request, function ($req) {
            $this->assertEquals('/users', $req->getUri()->getPath());

            return new Response(200);
        });
    }

    public function test_it_rewrites_path_adding_prefix()
    {
        $route = (new Route('/users/*', 'https://backend.example.com'))
            ->middleware(new RewritePath('/api/v2/users/$1'));

        $route->matches(new ServerRequest('GET', 'https://example.com/users/123'));

        $middlewares = $route->getMiddlewares();
        $request = new ServerRequest('GET', 'https://backend.example.com/users/123');

        $middlewares[0]->process($request, function ($req) {
            $this->assertEquals('/api/v2/users/123', $req->getUri()->getPath());

            return new Response(200);
        });
    }

    public function test_it_preserves_query_string()
    {
        $route = (new Route('/api/v1/*', 'https://backend.example.com'))
            ->middleware(new RewritePath('/v1/$1'));

        $route->matches(new ServerRequest('GET', 'https://example.com/api/v1/users?page=2&limit=10'));

        $middlewares = $route->getMiddlewares();
        $request = new ServerRequest('GET', 'https://backend.example.com/api/v1/users?page=2&limit=10');

        $middlewares[0]->process($request, function ($req) {
            $this->assertEquals('/v1/users', $req->getUri()->getPath());
            $this->assertEquals('page=2&limit=10', $req->getUri()->getQuery());

            return new Response(200);
        });
    }

    public function test_it_handles_no_captures()
    {
        $route = (new Route('/old-endpoint', 'https://backend.example.com'))
            ->middleware(new RewritePath('/new-endpoint'));

        $route->matches(new ServerRequest('GET', 'https://example.com/old-endpoint'));

        $middlewares = $route->getMiddlewares();
        $request = new ServerRequest('GET', 'https://backend.example.com/old-endpoint');

        $middlewares[0]->process($request, function ($req) {
            $this->assertEquals('/new-endpoint', $req->getUri()->getPath());

            return new Response(200);
        });
    }

    public function test_it_handles_multiple_captures()
    {
        $route = (new Route('/api/*/resources/*', 'https://backend.example.com'))
            ->middleware(new RewritePath('/v2/$1/items/$2'));

        $route->matches(new ServerRequest('GET', 'https://example.com/api/users/resources/123'));

        $middlewares = $route->getMiddlewares();
        $request = new ServerRequest('GET', 'https://backend.example.com/api/users/resources/123');

        $middlewares[0]->process($request, function ($req) {
            $this->assertEquals('/v2/users/items/123', $req->getUri()->getPath());

            return new Response(200);
        });
    }

    public function test_it_preserves_host_header_when_rewriting_path()
    {
        $route = (new Route('/api/*', 'https://172.17.0.1'))
            ->middleware(new RewritePath('/v2/$1'));

        $route->matches(new ServerRequest('GET', 'https://example.com/api/users'));

        $middlewares = $route->getMiddlewares();
        $request = (new ServerRequest('GET', 'https://172.17.0.1/api/users'))
            ->withHeader('Host', 'custom-host.example.com');

        $middlewares[0]->process($request, function ($req) {
            $this->assertEquals('/v2/users', $req->getUri()->getPath());
            $this->assertEquals('custom-host.example.com', $req->getHeaderLine('Host'));

            return new Response(200);
        });
    }
}
