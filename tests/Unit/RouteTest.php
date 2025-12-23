<?php

namespace ReverseProxy\Tests\Unit;

use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use ReverseProxy\MiddlewareInterface;
use ReverseProxy\Route;

class RouteTest extends TestCase
{
    public function test_it_matches_exact_path()
    {
        $route = new Route('/api/users', 'https://backend.example.com');
        $request = new ServerRequest('GET', '/api/users');

        $result = $route->matches($request);

        $this->assertEquals('https://backend.example.com/api/users', $result);
    }

    public function test_it_returns_null_when_path_does_not_match()
    {
        $route = new Route('/api/users', 'https://backend.example.com');
        $request = new ServerRequest('GET', '/about');

        $result = $route->matches($request);

        $this->assertNull($result);
    }

    public function test_it_matches_wildcard_pattern()
    {
        $route = new Route('/api/*', 'https://backend.example.com');
        $request = new ServerRequest('GET', '/api/users/123');

        $result = $route->matches($request);

        $this->assertEquals('https://backend.example.com/api/users/123', $result);
    }

    public function test_it_preserves_query_string()
    {
        $route = new Route('/api/*', 'https://backend.example.com');
        $request = new ServerRequest('GET', '/api/users?page=2&limit=10');

        $result = $route->matches($request);

        $this->assertEquals('https://backend.example.com/api/users?page=2&limit=10', $result);
    }

    public function test_it_returns_target_host()
    {
        $route = new Route('/api/*', 'https://backend.example.com');

        $this->assertEquals('backend.example.com', $route->getTargetHost());
    }

    public function test_it_has_no_middlewares_by_default()
    {
        $route = new Route('/api/*', 'https://backend.example.com');

        $this->assertEmpty($route->getMiddlewares());
    }

    public function test_it_accepts_middlewares_in_constructor()
    {
        $middleware1 = function ($request, $next) {
            return $next($request);
        };
        $middleware2 = function ($request, $next) {
            return $next($request);
        };

        $route = new Route('/api/*', 'https://backend.example.com', [$middleware1, $middleware2]);

        $this->assertCount(2, $route->getMiddlewares());
    }

    public function test_it_can_add_middleware_via_method()
    {
        $middleware = function ($request, $next) {
            return $next($request);
        };

        $route = (new Route('/api/*', 'https://backend.example.com'))
            ->middleware($middleware);

        $this->assertCount(1, $route->getMiddlewares());
    }

    public function test_it_can_chain_multiple_middlewares()
    {
        $middleware1 = function ($request, $next) {
            return $next($request);
        };
        $middleware2 = function ($request, $next) {
            return $next($request);
        };

        $route = (new Route('/api/*', 'https://backend.example.com'))
            ->middleware($middleware1)
            ->middleware($middleware2);

        $this->assertCount(2, $route->getMiddlewares());
    }

    public function test_it_combines_constructor_and_method_middlewares()
    {
        $middleware1 = function ($request, $next) {
            return $next($request);
        };
        $middleware2 = function ($request, $next) {
            return $next($request);
        };

        $route = (new Route('/api/*', 'https://backend.example.com', [$middleware1]))
            ->middleware($middleware2);

        $this->assertCount(2, $route->getMiddlewares());
    }

    public function test_it_can_add_middleware_interface()
    {
        $middleware = new class implements MiddlewareInterface {
            public function process(RequestInterface $request, callable $next): ResponseInterface
            {
                return $next($request->withHeader('X-Test', 'value'));
            }
        };

        $route = (new Route('/api/*', 'https://backend.example.com'))
            ->middleware($middleware);

        $this->assertCount(1, $route->getMiddlewares());
    }

    public function test_it_accepts_middleware_interface_in_constructor()
    {
        $middleware = new class implements MiddlewareInterface {
            public function process(RequestInterface $request, callable $next): ResponseInterface
            {
                return $next($request->withHeader('X-Test', 'value'));
            }
        };

        $route = new Route('/api/*', 'https://backend.example.com', [$middleware]);

        $this->assertCount(1, $route->getMiddlewares());
    }

    public function test_it_matches_specific_http_method()
    {
        $route = new Route('POST /api/users', 'https://backend.example.com');
        $request = new ServerRequest('POST', '/api/users');

        $result = $route->matches($request);

        $this->assertEquals('https://backend.example.com/api/users', $result);
    }

    public function test_it_returns_null_when_method_does_not_match()
    {
        $route = new Route('POST /api/users', 'https://backend.example.com');
        $request = new ServerRequest('GET', '/api/users');

        $result = $route->matches($request);

        $this->assertNull($result);
    }

    public function test_it_matches_get_method_with_wildcard_path()
    {
        $route = new Route('GET /api/*', 'https://backend.example.com');
        $request = new ServerRequest('GET', '/api/users/123');

        $result = $route->matches($request);

        $this->assertEquals('https://backend.example.com/api/users/123', $result);
    }

    public function test_it_handles_case_insensitive_method()
    {
        $route = new Route('post /api/users', 'https://backend.example.com');
        $request = new ServerRequest('POST', '/api/users');

        $result = $route->matches($request);

        $this->assertEquals('https://backend.example.com/api/users', $result);
    }

    public function test_path_only_source_matches_all_methods()
    {
        $route = new Route('/api/*', 'https://backend.example.com');

        $getRequest = new ServerRequest('GET', '/api/users');
        $postRequest = new ServerRequest('POST', '/api/users');
        $deleteRequest = new ServerRequest('DELETE', '/api/users');

        $this->assertNotNull($route->matches($getRequest));
        $this->assertNotNull($route->matches($postRequest));
        $this->assertNotNull($route->matches($deleteRequest));
    }

    public function test_it_matches_delete_method()
    {
        $route = new Route('DELETE /api/users/*', 'https://backend.example.com');
        $request = new ServerRequest('DELETE', '/api/users/123');

        $result = $route->matches($request);

        $this->assertEquals('https://backend.example.com/api/users/123', $result);
    }

    public function test_it_matches_multiple_methods_with_pipe()
    {
        $route = new Route('GET|POST /api/users', 'https://backend.example.com');

        $getRequest = new ServerRequest('GET', '/api/users');
        $postRequest = new ServerRequest('POST', '/api/users');
        $deleteRequest = new ServerRequest('DELETE', '/api/users');

        $this->assertNotNull($route->matches($getRequest));
        $this->assertNotNull($route->matches($postRequest));
        $this->assertNull($route->matches($deleteRequest));
    }

    public function test_it_matches_multiple_methods_with_wildcard_path()
    {
        $route = new Route('GET|POST|PUT /api/*', 'https://backend.example.com');
        $request = new ServerRequest('PUT', '/api/users/123');

        $result = $route->matches($request);

        $this->assertEquals('https://backend.example.com/api/users/123', $result);
    }
}
