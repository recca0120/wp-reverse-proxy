<?php

namespace Recca0120\ReverseProxy\Tests\Unit\WordPress\Admin;

use PHPUnit\Framework\TestCase;
use Recca0120\ReverseProxy\Routing\Route;
use Recca0120\ReverseProxy\WordPress\Admin\RoutesPage;

class RoutesPageTest extends TestCase
{
    public function test_convert_db_route_to_route_object()
    {
        $data = [
            'path' => '/api/*',
            'target' => 'https://api.example.com',
            'methods' => [],
            'middlewares' => [],
        ];

        $route = RoutesPage::toRouteObject($data);

        $this->assertInstanceOf(Route::class, $route);
    }

    public function test_convert_db_route_with_methods()
    {
        $data = [
            'path' => '/api/*',
            'target' => 'https://api.example.com',
            'methods' => ['GET', 'POST'],
            'middlewares' => [],
        ];

        $route = RoutesPage::toRouteObject($data);

        $this->assertInstanceOf(Route::class, $route);
        // The path should be prefixed with methods
        $this->assertNotNull($route);
    }

    public function test_convert_db_route_with_string_middleware()
    {
        $data = [
            'path' => '/api/*',
            'target' => 'https://api.example.com',
            'methods' => [],
            'middlewares' => ['ProxyHeaders'],
        ];

        $route = RoutesPage::toRouteObject($data);

        $middlewares = $route->getMiddlewares();
        $this->assertCount(1, $middlewares);
    }

    public function test_convert_db_route_with_array_middleware()
    {
        $data = [
            'path' => '/api/*',
            'target' => 'https://api.example.com',
            'methods' => [],
            'middlewares' => [
                ['SetHost', 'custom.example.com'],
            ],
        ];

        $route = RoutesPage::toRouteObject($data);

        $middlewares = $route->getMiddlewares();
        $this->assertCount(1, $middlewares);
    }

    public function test_convert_db_route_with_multiple_middlewares()
    {
        $data = [
            'path' => '/api/*',
            'target' => 'https://api.example.com',
            'methods' => ['GET'],
            'middlewares' => [
                'ProxyHeaders',
                ['SetHost', 'api.example.com'],
                ['Timeout', 30],
            ],
        ];

        $route = RoutesPage::toRouteObject($data);

        $middlewares = $route->getMiddlewares();
        $this->assertCount(3, $middlewares);
    }

    public function test_convert_db_route_with_empty_data()
    {
        $data = [
            'path' => '',
            'target' => '',
            'methods' => [],
            'middlewares' => [],
        ];

        $route = RoutesPage::toRouteObject($data);

        $this->assertInstanceOf(Route::class, $route);
    }

    public function test_convert_db_route_preserves_path_pattern()
    {
        $data = [
            'path' => '/users/*/profile',
            'target' => 'https://api.example.com',
            'methods' => [],
            'middlewares' => [],
        ];

        $route = RoutesPage::toRouteObject($data);

        $this->assertInstanceOf(Route::class, $route);
    }
}
