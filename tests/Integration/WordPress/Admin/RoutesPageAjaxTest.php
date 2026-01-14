<?php

namespace Recca0120\ReverseProxy\Tests\Integration\WordPress\Admin;

use Recca0120\ReverseProxy\WordPress\Admin\Admin;
use Recca0120\ReverseProxy\WordPress\Admin\RoutesPage;
use WP_Ajax_UnitTestCase;

class RoutesPageAjaxTest extends WP_Ajax_UnitTestCase
{
    private const OPTION_NAME = 'reverse_proxy_admin_routes';

    /** @var RoutesPage */
    private $routesPage;

    protected function setUp(): void
    {
        parent::setUp();

        add_filter('reverse_proxy_should_exit', '__return_false');

        // Set up admin user
        $admin_id = $this->factory->user->create(['role' => 'administrator']);
        wp_set_current_user($admin_id);

        $this->routesPage = new RoutesPage();

        // Initialize admin to register AJAX handlers
        $admin = new Admin($this->routesPage);
        $admin->register();
    }

    protected function tearDown(): void
    {
        delete_option(self::OPTION_NAME);
        remove_all_filters('reverse_proxy_should_exit');
        wp_set_current_user(0);
        parent::tearDown();
    }

    public function test_handle_form_submission_skips_during_ajax()
    {
        $_POST['action'] = 'reverse_proxy_save_route';
        $_POST['nonce'] = wp_create_nonce('reverse_proxy_admin');
        $_POST['path'] = '/ajax-test/*';
        $_POST['target'] = 'https://ajax-test.example.com';

        $routesBefore = get_option(self::OPTION_NAME, []);

        $admin = new Admin($this->routesPage);
        $admin->handleFormSubmission();

        $routesAfter = get_option(self::OPTION_NAME, []);

        // Routes should not change during AJAX - form submission was skipped
        $this->assertEquals($routesBefore, $routesAfter);

        unset($_POST['action'], $_POST['nonce'], $_POST['path'], $_POST['target']);
    }

    public function test_ajax_save_route_handler()
    {
        $_POST['nonce'] = wp_create_nonce('reverse_proxy_admin');
        $_POST['route'] = [
            'path' => '/ajax-api/*',
            'target' => 'https://ajax.example.com',
            'methods' => ['GET', 'POST'],
            'enabled' => '1',
        ];
        $_POST['middlewares_json'] = json_encode(['ProxyHeaders']);

        $response = $this->handleAjax('reverse_proxy_save_route');

        $this->assertTrue($response['success']);
        $this->assertEquals('Route saved successfully.', $response['data']['message']);

        $routes = $this->routesPage->getRoutes();
        $this->assertCount(1, $routes);
        $this->assertEquals('/ajax-api/*', $routes[0]['path']);
        $this->assertEquals('https://ajax.example.com', $routes[0]['target']);

        unset($_POST['nonce'], $_POST['route'], $_POST['middlewares_json']);
    }

    public function test_ajax_delete_route_handler()
    {
        $this->routesPage->saveRoute([
            'path' => '/to-delete/*',
            'target' => 'https://delete.example.com',
            'enabled' => true,
        ]);

        $routes = $this->routesPage->getRoutes();
        $routeId = $routes[0]['id'];

        $_POST['nonce'] = wp_create_nonce('reverse_proxy_admin');
        $_POST['route_id'] = $routeId;

        $response = $this->handleAjax('reverse_proxy_delete_route');

        $this->assertTrue($response['success']);
        $this->assertEquals('Route deleted successfully.', $response['data']['message']);

        $routes = $this->routesPage->getRoutes();
        $this->assertCount(0, $routes);

        unset($_POST['nonce'], $_POST['route_id']);
    }

    public function test_ajax_toggle_route_handler()
    {
        $this->routesPage->saveRoute([
            'path' => '/to-toggle/*',
            'target' => 'https://toggle.example.com',
            'enabled' => true,
        ]);

        $routes = $this->routesPage->getRoutes();
        $routeId = $routes[0]['id'];
        $this->assertTrue($routes[0]['enabled']);

        $_POST['nonce'] = wp_create_nonce('reverse_proxy_admin');
        $_POST['route_id'] = $routeId;

        $response = $this->handleAjax('reverse_proxy_toggle_route');

        $this->assertTrue($response['success']);

        $routes = $this->routesPage->getRoutes();
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

        $response = $this->handleAjax('reverse_proxy_save_route');

        $this->assertFalse($response['success']);
        $this->assertEquals('Security check failed.', $response['data']['message']);

        unset($_POST['nonce'], $_POST['route']);
    }

    public function test_ajax_save_requires_permission()
    {
        $subscriber_id = $this->factory->user->create(['role' => 'subscriber']);
        wp_set_current_user($subscriber_id);

        $_POST['nonce'] = wp_create_nonce('reverse_proxy_admin');
        $_POST['route'] = [
            'path' => '/api/*',
            'target' => 'https://api.example.com',
        ];

        $response = $this->handleAjax('reverse_proxy_save_route');

        $this->assertFalse($response['success']);
        $this->assertEquals('Permission denied.', $response['data']['message']);

        unset($_POST['nonce'], $_POST['route']);
    }

    public function test_save_route_with_json_middlewares()
    {
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

        $response = $this->handleAjax('reverse_proxy_save_route');

        $this->assertTrue($response['success']);

        $routes = $this->routesPage->getRoutes();
        $this->assertCount(1, $routes);
        $this->assertEquals([
            'ProxyHeaders',
            ['SetHost', 'custom.host.com'],
            ['Timeout', 60],
        ], $routes[0]['middlewares']);

        unset($_POST['nonce'], $_POST['route'], $_POST['middlewares_json']);
    }

    public function test_ajax_delete_route_requires_route_id()
    {
        $_POST['nonce'] = wp_create_nonce('reverse_proxy_admin');
        $_POST['route_id'] = '';

        $response = $this->handleAjax('reverse_proxy_delete_route');

        $this->assertFalse($response['success']);
        $this->assertEquals('Route ID is required.', $response['data']['message']);

        unset($_POST['nonce'], $_POST['route_id']);
    }

    public function test_ajax_toggle_route_requires_route_id()
    {
        $_POST['nonce'] = wp_create_nonce('reverse_proxy_admin');

        $response = $this->handleAjax('reverse_proxy_toggle_route');

        $this->assertFalse($response['success']);
        $this->assertEquals('Route ID is required.', $response['data']['message']);

        unset($_POST['nonce']);
    }

    public function test_ajax_import_routes_rejects_invalid_json()
    {
        $_POST['nonce'] = wp_create_nonce('reverse_proxy_admin');
        $_POST['mode'] = 'replace';
        $_POST['data'] = 'not valid json {{{';

        $response = $this->handleAjax('reverse_proxy_import_routes');

        $this->assertFalse($response['success']);
        $this->assertEquals('Invalid JSON data.', $response['data']['message']);

        unset($_POST['nonce'], $_POST['mode'], $_POST['data']);
    }

    public function test_ajax_export_routes_returns_json_response()
    {
        $this->routesPage->saveRoute([
            'path' => '/export-test/*',
            'target' => 'https://export.example.com',
            'enabled' => true,
        ]);

        $_GET['nonce'] = wp_create_nonce('reverse_proxy_admin');

        $response = $this->handleAjax('reverse_proxy_export_routes');

        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('version', $response['data']);
        $this->assertArrayHasKey('routes', $response['data']);

        unset($_GET['nonce']);
    }

    public function test_ajax_export_routes_requires_valid_nonce()
    {
        $_GET['nonce'] = 'invalid_nonce';

        $response = $this->handleAjax('reverse_proxy_export_routes');

        $this->assertFalse($response['success']);
        $this->assertEquals('Security check failed.', $response['data']['message']);

        unset($_GET['nonce']);
    }

    public function test_ajax_export_routes_requires_permission()
    {
        $subscriber_id = $this->factory->user->create(['role' => 'subscriber']);
        wp_set_current_user($subscriber_id);

        $_GET['nonce'] = wp_create_nonce('reverse_proxy_admin');

        $response = $this->handleAjax('reverse_proxy_export_routes');

        $this->assertFalse($response['success']);
        $this->assertEquals('Permission denied.', $response['data']['message']);

        unset($_GET['nonce']);
    }

    public function test_ajax_import_routes_with_merge_mode()
    {
        $this->routesPage->saveRoute([
            'path' => '/existing/*',
            'target' => 'https://existing.example.com',
            'enabled' => true,
        ]);

        $_POST['nonce'] = wp_create_nonce('reverse_proxy_admin');
        $_POST['mode'] = 'merge';
        $_POST['data'] = json_encode([
            'version' => '1.0',
            'routes' => [
                [
                    'path' => '/imported/*',
                    'target' => 'https://imported.example.com',
                    'enabled' => true,
                ],
            ],
        ]);

        $response = $this->handleAjax('reverse_proxy_import_routes');

        $this->assertTrue($response['success']);
        $this->assertEquals(1, $response['data']['imported']);

        $routes = $this->routesPage->getRoutes();
        $this->assertCount(2, $routes);

        unset($_POST['nonce'], $_POST['mode'], $_POST['data']);
    }

    public function test_ajax_import_routes_requires_valid_nonce()
    {
        $_POST['nonce'] = 'invalid_nonce';
        $_POST['mode'] = 'replace';
        $_POST['data'] = json_encode(['version' => '1.0', 'routes' => []]);

        $response = $this->handleAjax('reverse_proxy_import_routes');

        $this->assertFalse($response['success']);
        $this->assertEquals('Security check failed.', $response['data']['message']);

        unset($_POST['nonce'], $_POST['mode'], $_POST['data']);
    }

    public function test_ajax_import_routes_requires_permission()
    {
        $subscriber_id = $this->factory->user->create(['role' => 'subscriber']);
        wp_set_current_user($subscriber_id);

        $_POST['nonce'] = wp_create_nonce('reverse_proxy_admin');
        $_POST['mode'] = 'replace';
        $_POST['data'] = json_encode(['version' => '1.0', 'routes' => []]);

        $response = $this->handleAjax('reverse_proxy_import_routes');

        $this->assertFalse($response['success']);
        $this->assertEquals('Permission denied.', $response['data']['message']);

        unset($_POST['nonce'], $_POST['mode'], $_POST['data']);
    }

    /**
     * Handle AJAX request and return parsed response.
     */
    private function handleAjax(string $action): array
    {
        add_filter('wp_die_ajax_handler', function () {
            return function ($message) {
                throw new \WPDieException($message);
            };
        }, 99);

        ob_start();
        try {
            do_action('wp_ajax_' . $action);
        } catch (\WPDieException $e) {
            // Expected - AJAX responses call wp_die
        }
        $output = ob_get_clean();

        remove_all_filters('wp_die_ajax_handler');

        $response = json_decode($output, true);
        if ($response === null) {
            return ['success' => false, 'data' => ['message' => $output]];
        }

        return $response;
    }
}
