<?php

namespace Recca0120\ReverseProxy\WordPress\Admin;

class Admin
{
    /** @var RoutesPage */
    private $routesPage;

    public function __construct(?RoutesPage $routesPage = null)
    {
        $this->routesPage = $routesPage ?? new RoutesPage();
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenuPage']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);

        // AJAX handlers
        add_action('wp_ajax_reverse_proxy_save_route', [$this, 'handleSaveRoute']);
        add_action('wp_ajax_reverse_proxy_delete_route', [$this, 'handleDeleteRoute']);
        add_action('wp_ajax_reverse_proxy_toggle_route', [$this, 'handleToggleRoute']);
    }

    public function addMenuPage(): void
    {
        add_menu_page(
            __('Reverse Proxy', 'reverse-proxy'),
            __('Reverse Proxy', 'reverse-proxy'),
            'manage_options',
            'reverse-proxy',
            [$this->routesPage, 'render'],
            'dashicons-randomize',
            80
        );
    }

    public function enqueueAssets(string $hook): void
    {
        if ($hook !== 'toplevel_page_reverse-proxy') {
            return;
        }

        wp_enqueue_style(
            'reverse-proxy-admin',
            REVERSE_PROXY_PLUGIN_URL . 'assets/css/admin.css',
            [],
            REVERSE_PROXY_VERSION
        );

        wp_enqueue_script(
            'reverse-proxy-admin',
            REVERSE_PROXY_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            REVERSE_PROXY_VERSION,
            true
        );

        wp_localize_script('reverse-proxy-admin', 'reverseProxyAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('reverse_proxy_admin'),
            'middlewares' => MiddlewareRegistry::getAll(),
        ]);
    }

    public function handleSaveRoute(): void
    {
        $this->verifyAjaxRequest();

        $route = isset($_POST['route']) ? (array) $_POST['route'] : [];

        // Handle advanced mode JSON
        if (!empty($_POST['middlewares_json'])) {
            $json = stripslashes($_POST['middlewares_json']);
            $middlewares = json_decode($json, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($middlewares)) {
                $route['middlewares'] = $middlewares;
            }
        }

        $result = $this->routesPage->saveRoute($route);

        if ($result) {
            wp_send_json_success([
                'message' => __('Route saved successfully.', 'reverse-proxy'),
                'redirect' => admin_url('admin.php?page=reverse-proxy'),
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Failed to save route. Please check the path and target URL.', 'reverse-proxy'),
            ]);
        }
    }

    public function handleDeleteRoute(): void
    {
        $this->verifyAjaxRequest();

        $routeId = isset($_POST['route_id']) ? sanitize_text_field($_POST['route_id']) : '';

        if (empty($routeId)) {
            wp_send_json_error(['message' => __('Route ID is required.', 'reverse-proxy')]);
        }

        $result = $this->routesPage->deleteRoute($routeId);

        if ($result) {
            wp_send_json_success(['message' => __('Route deleted successfully.', 'reverse-proxy')]);
        } else {
            wp_send_json_error(['message' => __('Failed to delete route.', 'reverse-proxy')]);
        }
    }

    public function handleToggleRoute(): void
    {
        $this->verifyAjaxRequest();

        $routeId = isset($_POST['route_id']) ? sanitize_text_field($_POST['route_id']) : '';

        if (empty($routeId)) {
            wp_send_json_error(['message' => __('Route ID is required.', 'reverse-proxy')]);
        }

        $result = $this->routesPage->toggleRoute($routeId);

        if ($result) {
            wp_send_json_success(['message' => __('Route status updated.', 'reverse-proxy')]);
        } else {
            wp_send_json_error(['message' => __('Failed to update route status.', 'reverse-proxy')]);
        }
    }

    private function verifyAjaxRequest(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'reverse-proxy')], 403);
        }

        $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
        if (!wp_verify_nonce($nonce, 'reverse_proxy_admin')) {
            // Also check for the form nonce
            $formNonce = isset($_POST['reverse_proxy_nonce']) ? $_POST['reverse_proxy_nonce'] : '';
            if (!wp_verify_nonce($formNonce, 'reverse_proxy_save_route')) {
                wp_send_json_error(['message' => __('Security check failed.', 'reverse-proxy')], 403);
            }
        }
    }
}
