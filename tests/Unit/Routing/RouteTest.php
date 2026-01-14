<?php

namespace Recca0120\ReverseProxy\Tests\Unit\Routing;

use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Recca0120\ReverseProxy\Contracts\MiddlewareInterface;
use Recca0120\ReverseProxy\Routing\Route;

class RouteTest extends TestCase
{
    public function test_matches_exact_path()
    {
        $route = new Route('/api/users', 'https://backend.example.com');
        $request = new ServerRequest('GET', '/api/users');

        $result = $route->matches($request);

        $this->assertEquals('https://backend.example.com/api/users', $result);
    }

    public function test_returns_null_when_path_does_not_match()
    {
        $route = new Route('/api/users', 'https://backend.example.com');
        $request = new ServerRequest('GET', '/about');

        $result = $route->matches($request);

        $this->assertNull($result);
    }

    public function test_matches_wildcard_pattern()
    {
        $route = new Route('/api/*', 'https://backend.example.com');
        $request = new ServerRequest('GET', '/api/users/123');

        $result = $route->matches($request);

        $this->assertEquals('https://backend.example.com/api/users/123', $result);
    }

    public function test_preserves_query_string()
    {
        $route = new Route('/api/*', 'https://backend.example.com');
        $request = new ServerRequest('GET', '/api/users?page=2&limit=10');

        $result = $route->matches($request);

        $this->assertEquals('https://backend.example.com/api/users?page=2&limit=10', $result);
    }

    public function test_returns_target_host()
    {
        $route = new Route('/api/*', 'https://backend.example.com');

        $this->assertEquals('backend.example.com', $route->getTargetHost());
    }

    public function test_has_no_middlewares_by_default()
    {
        $route = new Route('/api/*', 'https://backend.example.com');

        $this->assertEmpty($route->getMiddlewares());
    }

    public function test_accepts_middlewares_in_constructor()
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

    public function test_can_add_middleware_via_method()
    {
        $middleware = function ($request, $next) {
            return $next($request);
        };

        $route = (new Route('/api/*', 'https://backend.example.com'))
            ->middleware($middleware);

        $this->assertCount(1, $route->getMiddlewares());
    }

    public function test_can_chain_multiple_middlewares()
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

    public function test_combines_constructor_and_method_middlewares()
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

    public function test_can_add_middleware_interface()
    {
        $middleware = new class () implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, callable $next): ResponseInterface
            {
                return $next($request->withHeader('X-Test', 'value'));
            }
        };

        $route = (new Route('/api/*', 'https://backend.example.com'))
            ->middleware($middleware);

        $this->assertCount(1, $route->getMiddlewares());
    }

    public function test_accepts_middleware_interface_in_constructor()
    {
        $middleware = new class () implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, callable $next): ResponseInterface
            {
                return $next($request->withHeader('X-Test', 'value'));
            }
        };

        $route = new Route('/api/*', 'https://backend.example.com', [$middleware]);

        $this->assertCount(1, $route->getMiddlewares());
    }

    public function test_matches_specific_http_method()
    {
        $route = new Route('POST /api/users', 'https://backend.example.com');
        $request = new ServerRequest('POST', '/api/users');

        $result = $route->matches($request);

        $this->assertEquals('https://backend.example.com/api/users', $result);
    }

    public function test_returns_null_when_method_does_not_match()
    {
        $route = new Route('POST /api/users', 'https://backend.example.com');
        $request = new ServerRequest('GET', '/api/users');

        $result = $route->matches($request);

        $this->assertNull($result);
    }

    public function test_matches_get_method_with_wildcard_path()
    {
        $route = new Route('GET /api/*', 'https://backend.example.com');
        $request = new ServerRequest('GET', '/api/users/123');

        $result = $route->matches($request);

        $this->assertEquals('https://backend.example.com/api/users/123', $result);
    }

    public function test_handles_case_insensitive_method()
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

    public function test_matches_delete_method()
    {
        $route = new Route('DELETE /api/users/*', 'https://backend.example.com');
        $request = new ServerRequest('DELETE', '/api/users/123');

        $result = $route->matches($request);

        $this->assertEquals('https://backend.example.com/api/users/123', $result);
    }

    public function test_matches_multiple_methods_with_pipe()
    {
        $route = new Route('GET|POST /api/users', 'https://backend.example.com');

        $getRequest = new ServerRequest('GET', '/api/users');
        $postRequest = new ServerRequest('POST', '/api/users');
        $deleteRequest = new ServerRequest('DELETE', '/api/users');

        $this->assertNotNull($route->matches($getRequest));
        $this->assertNotNull($route->matches($postRequest));
        $this->assertNull($route->matches($deleteRequest));
    }

    public function test_matches_multiple_methods_with_wildcard_path()
    {
        $route = new Route('GET|POST|PUT /api/*', 'https://backend.example.com');
        $request = new ServerRequest('PUT', '/api/users/123');

        $result = $route->matches($request);

        $this->assertEquals('https://backend.example.com/api/users/123', $result);
    }

    public function test_sorts_middlewares_by_priority()
    {
        $lowPriority = new class () implements MiddlewareInterface {
            public $priority = 10;

            public function process(ServerRequestInterface $request, callable $next): ResponseInterface
            {
                return $next($request);
            }
        };

        $highPriority = new class () implements MiddlewareInterface {
            public $priority = -100;

            public function process(ServerRequestInterface $request, callable $next): ResponseInterface
            {
                return $next($request);
            }
        };

        $route = new Route('/api/*', 'https://backend.example.com', [$lowPriority, $highPriority]);

        $middlewares = $route->getMiddlewares();

        // highPriority (-100) should come before lowPriority (10)
        $this->assertSame($highPriority, $middlewares[0]);
        $this->assertSame($lowPriority, $middlewares[1]);
    }

    public function test_uses_zero_priority_for_middlewares_without_priority()
    {
        $withPriority = new class () implements MiddlewareInterface {
            public $priority = -50;

            public function process(ServerRequestInterface $request, callable $next): ResponseInterface
            {
                return $next($request);
            }
        };

        $withoutPriority = new class () implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, callable $next): ResponseInterface
            {
                return $next($request);
            }
        };

        $route = new Route('/api/*', 'https://backend.example.com', [$withoutPriority, $withPriority]);

        $middlewares = $route->getMiddlewares();

        // withPriority (-50) should come before withoutPriority (0)
        $this->assertSame($withPriority, $middlewares[0]);
        $this->assertSame($withoutPriority, $middlewares[1]);
    }

    public function test_uses_zero_priority_for_closure_middlewares()
    {
        $highPriority = new class () implements MiddlewareInterface {
            public $priority = -100;

            public function process(ServerRequestInterface $request, callable $next): ResponseInterface
            {
                return $next($request);
            }
        };

        $closure = function ($request, $next) {
            return $next($request);
        };

        $route = new Route('/api/*', 'https://backend.example.com', [$closure, $highPriority]);

        $middlewares = $route->getMiddlewares();

        // highPriority (-100) should come before closure (0)
        $this->assertSame($highPriority, $middlewares[0]);
        $this->assertSame($closure, $middlewares[1]);
    }

    public function test_maintains_order_for_same_priority()
    {
        $first = new class () implements MiddlewareInterface {
            public $priority = 0;

            public $name = 'first';

            public function process(ServerRequestInterface $request, callable $next): ResponseInterface
            {
                return $next($request);
            }
        };

        $second = new class () implements MiddlewareInterface {
            public $priority = 0;

            public $name = 'second';

            public function process(ServerRequestInterface $request, callable $next): ResponseInterface
            {
                return $next($request);
            }
        };

        $route = new Route('/api/*', 'https://backend.example.com', [$first, $second]);

        $middlewares = $route->getMiddlewares();

        $this->assertEquals('first', $middlewares[0]->name);
        $this->assertEquals('second', $middlewares[1]->name);
    }

    // Nginx-style trailing slash tests

    public function test_target_without_trailing_slash_keeps_full_path()
    {
        $route = new Route('/google/*', 'https://www.google.com');
        $request = new ServerRequest('GET', '/google/search');

        $result = $route->matches($request);

        $this->assertEquals('https://www.google.com/google/search', $result);
    }

    public function test_target_with_trailing_slash_strips_prefix()
    {
        $route = new Route('/google/*', 'https://www.google.com/');
        $request = new ServerRequest('GET', '/google/search');

        $result = $route->matches($request);

        $this->assertEquals('https://www.google.com/search', $result);
    }

    public function test_target_with_trailing_slash_strips_prefix_and_preserves_query()
    {
        $route = new Route('/google/*', 'https://www.google.com/');
        $request = new ServerRequest('GET', '/google/search?q=test&lang=en');

        $result = $route->matches($request);

        $this->assertEquals('https://www.google.com/search?q=test&lang=en', $result);
    }

    public function test_target_with_trailing_slash_handles_root_path()
    {
        $route = new Route('/google/*', 'https://www.google.com/');
        $request = new ServerRequest('GET', '/google/');

        $result = $route->matches($request);

        $this->assertEquals('https://www.google.com/', $result);
    }

    public function test_target_with_trailing_slash_handles_exact_match()
    {
        $route = new Route('/google', 'https://www.google.com/');
        $request = new ServerRequest('GET', '/google');

        $result = $route->matches($request);

        $this->assertEquals('https://www.google.com/', $result);
    }

    public function test_target_with_trailing_slash_handles_nested_path()
    {
        $route = new Route('/api/v1/*', 'https://backend.example.com/');
        $request = new ServerRequest('GET', '/api/v1/users/123/profile');

        $result = $route->matches($request);

        $this->assertEquals('https://backend.example.com/users/123/profile', $result);
    }

    public function test_target_with_trailing_slash_and_target_path()
    {
        $route = new Route('/old-api/*', 'https://backend.example.com/new-api/');
        $request = new ServerRequest('GET', '/old-api/users');

        $result = $route->matches($request);

        $this->assertEquals('https://backend.example.com/new-api/users', $result);
    }

    // Wildcard pattern should also match base path without trailing slash

    public function test_wildcard_pattern_matches_base_path_without_trailing_slash()
    {
        $route = new Route('/test/*', 'https://example.com/');
        $request = new ServerRequest('GET', '/test');

        $result = $route->matches($request);

        $this->assertEquals('https://example.com/', $result);
    }

    public function test_wildcard_pattern_matches_base_path_with_trailing_slash()
    {
        $route = new Route('/test/*', 'https://example.com/');
        $request = new ServerRequest('GET', '/test/');

        $result = $route->matches($request);

        $this->assertEquals('https://example.com/', $result);
    }

    public function test_wildcard_pattern_matches_subpath()
    {
        $route = new Route('/test/*', 'https://example.com/');
        $request = new ServerRequest('GET', '/test/foo/bar');

        $result = $route->matches($request);

        $this->assertEquals('https://example.com/foo/bar', $result);
    }

    public function test_wildcard_pattern_without_trailing_slash_target_matches_base_path()
    {
        $route = new Route('/api/*', 'https://backend.example.com');
        $request = new ServerRequest('GET', '/api');

        $result = $route->matches($request);

        $this->assertEquals('https://backend.example.com/api', $result);
    }

    public function test_nested_wildcard_pattern_matches_base_path()
    {
        $route = new Route('/api/v1/*', 'https://backend.example.com/');
        $request = new ServerRequest('GET', '/api/v1');

        $result = $route->matches($request);

        $this->assertEquals('https://backend.example.com/', $result);
    }

    public function test_wildcard_with_method_matches_base_path()
    {
        $route = new Route('GET /admin/*', 'https://admin.example.com/');
        $request = new ServerRequest('GET', '/admin');

        $result = $route->matches($request);

        $this->assertEquals('https://admin.example.com/', $result);
    }

    // Path without wildcard should act as prefix match (like /path/*)

    public function test_path_without_wildcard_matches_exact_path()
    {
        $route = new Route('/api', 'https://api.example.com/');
        $request = new ServerRequest('GET', '/api');

        $result = $route->matches($request);

        $this->assertEquals('https://api.example.com/', $result);
    }

    public function test_path_without_wildcard_matches_with_trailing_slash()
    {
        $route = new Route('/api', 'https://api.example.com/');
        $request = new ServerRequest('GET', '/api/');

        $result = $route->matches($request);

        $this->assertEquals('https://api.example.com/', $result);
    }

    public function test_path_without_wildcard_matches_subpaths()
    {
        $route = new Route('/api', 'https://api.example.com/');
        $request = new ServerRequest('GET', '/api/users/123');

        $result = $route->matches($request);

        $this->assertEquals('https://api.example.com/users/123', $result);
    }

    public function test_path_without_wildcard_does_not_match_similar_prefix()
    {
        $route = new Route('/api', 'https://api.example.com/');
        $request = new ServerRequest('GET', '/api-docs');

        $result = $route->matches($request);

        $this->assertNull($result);
    }

    public function test_nested_path_without_wildcard_matches_subpaths()
    {
        $route = new Route('/proxy/backend', 'https://backend.example.com/');
        $request = new ServerRequest('GET', '/proxy/backend/users/list');

        $result = $route->matches($request);

        $this->assertEquals('https://backend.example.com/users/list', $result);
    }

    public function test_path_with_method_without_wildcard_matches_subpaths()
    {
        $route = new Route('GET /admin', 'https://admin.example.com/');
        $request = new ServerRequest('GET', '/admin/dashboard');

        $result = $route->matches($request);

        $this->assertEquals('https://admin.example.com/dashboard', $result);
    }
}
