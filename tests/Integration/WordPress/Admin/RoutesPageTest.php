<?php

namespace Recca0120\ReverseProxy\Tests\Integration\WordPress\Admin;

use Http\Mock\Client as MockClient;
use Nyholm\Psr7\Response;
use Recca0120\ReverseProxy\Routing\Route;
use Recca0120\ReverseProxy\WordPress\Admin\Admin;
use Recca0120\ReverseProxy\WordPress\Admin\RoutesPage;
use WP_UnitTestCase;

class RoutesPageTest extends WP_UnitTestCase
{
    private const OPTION_NAME = 'reverse_proxy_admin_routes';

    /** @var MockClient */
    private $mockClient;

    /** @var Admin */
    private $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockClient = new MockClient();

        add_filter('reverse_proxy_http_client', function () {
            return $this->mockClient;
        });

        add_filter('reverse_proxy_should_exit', '__return_false');

        // Set up admin user
        $admin_id = $this->factory->user->create(['role' => 'administrator']);
        wp_set_current_user($admin_id);

        // Initialize admin
        $this->admin = new Admin();
        $this->admin->register();
    }

    protected function tearDown(): void
    {
        delete_option(self::OPTION_NAME);
        remove_all_filters('reverse_proxy_http_client');
        remove_all_filters('reverse_proxy_should_exit');
        remove_all_filters('reverse_proxy_routes');
        wp_set_current_user(0);
        parent::tearDown();
    }

    public function test_admin_menu_is_registered()
    {
        do_action('admin_menu');

        global $menu;

        $menu_slugs = array_column($menu ?? [], 2);
        $this->assertContains('reverse-proxy', $menu_slugs);
    }

    public function test_it_can_save_route()
    {
        $routesPage = new RoutesPage();

        $route = [
            'path' => '/api/*',
            'target' => 'https://api.example.com',
            'methods' => ['GET', 'POST'],
            'middlewares' => ['ProxyHeaders'],
            'enabled' => true,
        ];

        $result = $routesPage->saveRoute($route);

        $this->assertTrue($result);
        $routes = $routesPage->getRoutes();
        $this->assertCount(1, $routes);
        $this->assertEquals('/api/*', $routes[0]['path']);
        $this->assertEquals('https://api.example.com', $routes[0]['target']);
    }

    public function test_it_can_update_existing_route()
    {
        $routesPage = new RoutesPage();

        // Save initial route
        $route = [
            'path' => '/api/*',
            'target' => 'https://api.example.com',
            'enabled' => true,
        ];
        $routesPage->saveRoute($route);

        // Get the saved route's ID
        $routes = $routesPage->getRoutes();
        $routeId = $routes[0]['id'];

        // Update the route
        $updated = [
            'id' => $routeId,
            'path' => '/api/v2/*',
            'target' => 'https://api-v2.example.com',
            'enabled' => true,
        ];
        $routesPage->saveRoute($updated);

        $routes = $routesPage->getRoutes();
        $this->assertCount(1, $routes);
        $this->assertEquals('/api/v2/*', $routes[0]['path']);
        $this->assertEquals('https://api-v2.example.com', $routes[0]['target']);
    }

    public function test_it_can_delete_route()
    {
        $routesPage = new RoutesPage();

        // Save a route
        $routesPage->saveRoute([
            'path' => '/api/*',
            'target' => 'https://api.example.com',
            'enabled' => true,
        ]);

        $routes = $routesPage->getRoutes();
        $routeId = $routes[0]['id'];

        // Delete the route
        $result = $routesPage->deleteRoute($routeId);

        $this->assertTrue($result);
        $this->assertCount(0, $routesPage->getRoutes());
    }

    public function test_it_can_toggle_route_status()
    {
        $routesPage = new RoutesPage();

        // Save an enabled route
        $routesPage->saveRoute([
            'path' => '/api/*',
            'target' => 'https://api.example.com',
            'enabled' => true,
        ]);

        $routes = $routesPage->getRoutes();
        $routeId = $routes[0]['id'];
        $this->assertTrue($routes[0]['enabled']);

        // Toggle status
        $routesPage->toggleRoute($routeId);

        $routes = $routesPage->getRoutes();
        $this->assertFalse($routes[0]['enabled']);

        // Toggle again
        $routesPage->toggleRoute($routeId);

        $routes = $routesPage->getRoutes();
        $this->assertTrue($routes[0]['enabled']);
    }

    public function test_saved_routes_are_used_by_reverse_proxy()
    {
        $routesPage = new RoutesPage();

        // Save a route via admin
        $routesPage->saveRoute([
            'path' => '/admin-api/*',
            'target' => 'https://admin-backend.example.com',
            'methods' => ['GET'],
            'middlewares' => [],
            'enabled' => true,
        ]);

        // Register the filter to merge admin routes
        $routesPage->registerRoutesFilter();

        // Set up mock response
        $this->mockClient->addResponse(new Response(200, [], '{"from":"admin"}'));

        // Make request
        ob_start();
        $this->go_to('/admin-api/test');
        $output = ob_get_clean();

        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertNotFalse($lastRequest);
        $this->assertEquals('https://admin-backend.example.com/admin-api/test', (string) $lastRequest->getUri());
        $this->assertEquals('{"from":"admin"}', $output);
    }

    public function test_disabled_routes_are_not_proxied()
    {
        $routesPage = new RoutesPage();

        // Save a disabled route
        $routesPage->saveRoute([
            'path' => '/disabled-api/*',
            'target' => 'https://disabled.example.com',
            'enabled' => false,
        ]);

        $routesPage->registerRoutesFilter();

        $this->go_to('/disabled-api/test');

        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertFalse($lastRequest, 'Disabled route should not proxy');
    }

    public function test_sanitize_route_removes_invalid_data()
    {
        $routesPage = new RoutesPage();

        $input = [
            'path' => '<script>alert("xss")</script>/api/*',
            'target' => 'javascript:alert("xss")',
            'methods' => ['GET', 'INVALID', 'POST'],
            'enabled' => 'yes',
            'extra_field' => 'should be removed',
        ];

        $sanitized = $routesPage->sanitizeRoute($input);

        $this->assertStringNotContainsString('<script>', $sanitized['path']);
        $this->assertEmpty($sanitized['target']); // Invalid URL should be empty
        $this->assertEquals(['GET', 'POST'], $sanitized['methods']);
        $this->assertTrue($sanitized['enabled']);
        $this->assertArrayNotHasKey('extra_field', $sanitized);
    }

    public function test_route_requires_valid_path()
    {
        $routesPage = new RoutesPage();

        $result = $routesPage->saveRoute([
            'path' => '',
            'target' => 'https://api.example.com',
            'enabled' => true,
        ]);

        $this->assertFalse($result);
    }

    public function test_route_requires_valid_target_url()
    {
        $routesPage = new RoutesPage();

        $result = $routesPage->saveRoute([
            'path' => '/api/*',
            'target' => 'not-a-valid-url',
            'enabled' => true,
        ]);

        $this->assertFalse($result);
    }

    public function test_middlewares_are_converted_to_route_object()
    {
        $routesPage = new RoutesPage();

        $routesPage->saveRoute([
            'path' => '/api/*',
            'target' => 'https://api.example.com',
            'middlewares' => [
                'ProxyHeaders',
                ['SetHost', 'custom.example.com'],
                ['Timeout', 30],
            ],
            'enabled' => true,
        ]);

        $routes = $routesPage->getRoutes();
        $routeObject = RoutesPage::toRouteObject($routes[0]);

        $this->assertInstanceOf(Route::class, $routeObject);
        $middlewares = $routeObject->getMiddlewares();
        $this->assertCount(3, $middlewares);
    }

    public function test_ajax_save_route_handler()
    {
        $routesPage = new RoutesPage();

        // Set up POST data for AJAX
        $_POST['nonce'] = wp_create_nonce('reverse_proxy_admin');
        $_POST['route'] = [
            'path' => '/ajax-api/*',
            'target' => 'https://ajax.example.com',
            'methods' => ['GET', 'POST'],
            'enabled' => '1',
        ];
        $_POST['middlewares_json'] = json_encode(['ProxyHeaders']);

        // Capture AJAX response with output buffering and custom die handler
        $response = $this->captureAjaxResponse([$this->admin, 'handleSaveRoute']);

        $this->assertTrue($response['success']);
        $this->assertEquals('Route saved successfully.', $response['data']['message']);

        $routes = $routesPage->getRoutes();
        $this->assertCount(1, $routes);
        $this->assertEquals('/ajax-api/*', $routes[0]['path']);
        $this->assertEquals('https://ajax.example.com', $routes[0]['target']);

        unset($_POST['nonce'], $_POST['route'], $_POST['middlewares_json']);
    }

    public function test_ajax_delete_route_handler()
    {
        $routesPage = new RoutesPage();

        // First save a route
        $routesPage->saveRoute([
            'path' => '/to-delete/*',
            'target' => 'https://delete.example.com',
            'enabled' => true,
        ]);

        $routes = $routesPage->getRoutes();
        $routeId = $routes[0]['id'];

        // Set up POST data for AJAX delete
        $_POST['nonce'] = wp_create_nonce('reverse_proxy_admin');
        $_POST['route_id'] = $routeId;

        $response = $this->captureAjaxResponse([$this->admin, 'handleDeleteRoute']);

        $this->assertTrue($response['success']);
        $this->assertEquals('Route deleted successfully.', $response['data']['message']);

        $routes = $routesPage->getRoutes();
        $this->assertCount(0, $routes);

        unset($_POST['nonce'], $_POST['route_id']);
    }

    public function test_ajax_toggle_route_handler()
    {
        $routesPage = new RoutesPage();

        // First save an enabled route
        $routesPage->saveRoute([
            'path' => '/to-toggle/*',
            'target' => 'https://toggle.example.com',
            'enabled' => true,
        ]);

        $routes = $routesPage->getRoutes();
        $routeId = $routes[0]['id'];
        $this->assertTrue($routes[0]['enabled']);

        // Set up POST data for AJAX toggle
        $_POST['nonce'] = wp_create_nonce('reverse_proxy_admin');
        $_POST['route_id'] = $routeId;

        $response = $this->captureAjaxResponse([$this->admin, 'handleToggleRoute']);

        $this->assertTrue($response['success']);

        $routes = $routesPage->getRoutes();
        $this->assertFalse($routes[0]['enabled']);

        unset($_POST['nonce'], $_POST['route_id']);
    }

    public function test_ajax_save_requires_valid_nonce()
    {
        $_POST['nonce'] = 'invalid_nonce';
        $_POST['route'] = [
            'path' => '/api/*',
            'target' => 'https://api.example.com',
        ];

        $response = $this->captureAjaxResponse([$this->admin, 'handleSaveRoute']);

        $this->assertFalse($response['success']);
        $this->assertEquals('Security check failed.', $response['data']['message']);

        unset($_POST['nonce'], $_POST['route']);
    }

    public function test_ajax_save_requires_permission()
    {
        // Switch to subscriber (no manage_options capability)
        $subscriber_id = $this->factory->user->create(['role' => 'subscriber']);
        wp_set_current_user($subscriber_id);

        $_POST['nonce'] = wp_create_nonce('reverse_proxy_admin');
        $_POST['route'] = [
            'path' => '/api/*',
            'target' => 'https://api.example.com',
        ];

        $response = $this->captureAjaxResponse([$this->admin, 'handleSaveRoute']);

        $this->assertFalse($response['success']);
        $this->assertEquals('Permission denied.', $response['data']['message']);

        unset($_POST['nonce'], $_POST['route']);
    }

    public function test_save_route_with_json_middlewares()
    {
        $routesPage = new RoutesPage();

        $_POST['nonce'] = wp_create_nonce('reverse_proxy_admin');
        $_POST['route'] = [
            'path' => '/json-mw/*',
            'target' => 'https://json.example.com',
            'enabled' => '1',
        ];
        $_POST['middlewares_json'] = json_encode([
            'ProxyHeaders',
            ['SetHost', 'custom.host.com'],
            ['Timeout', 60],
        ]);

        $response = $this->captureAjaxResponse([$this->admin, 'handleSaveRoute']);

        $this->assertTrue($response['success']);

        $routes = $routesPage->getRoutes();
        $this->assertCount(1, $routes);
        $this->assertEquals([
            'ProxyHeaders',
            ['SetHost', 'custom.host.com'],
            ['Timeout', 60],
        ], $routes[0]['middlewares']);

        unset($_POST['nonce'], $_POST['route'], $_POST['middlewares_json']);
    }

    /**
     * Helper method to capture AJAX response without exiting
     */
    private function captureAjaxResponse(callable $callback): array
    {
        // Simulate AJAX context
        if (!defined('DOING_AJAX')) {
            define('DOING_AJAX', true);
        }

        // Replace die handler to throw exception instead of exiting
        add_filter('wp_die_ajax_handler', function () {
            return function ($message) {
                throw new \WPDieException($message);
            };
        }, 99);

        ob_start();
        try {
            $callback();
        } catch (\WPDieException $e) {
            // Expected - AJAX responses call wp_die
        }
        $output = ob_get_clean();

        // Clean up filter
        remove_all_filters('wp_die_ajax_handler');

        // Parse JSON response
        $response = json_decode($output, true);
        if ($response === null) {
            return ['success' => false, 'data' => ['message' => $output]];
        }

        return $response;
    }
}
