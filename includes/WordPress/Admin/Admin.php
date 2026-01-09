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
        add_action('admin_init', [$this, 'handleFormSubmission']);

        // Add settings link to plugin list
        add_filter('plugin_action_links_' . plugin_basename(REVERSE_PROXY_PLUGIN_FILE), [$this, 'addPluginActionLinks']);

        // AJAX handlers
        add_action('wp_ajax_reverse_proxy_save_route', [$this, 'handleSaveRoute']);
        add_action('wp_ajax_reverse_proxy_delete_route', [$this, 'handleDeleteRoute']);
        add_action('wp_ajax_reverse_proxy_toggle_route', [$this, 'handleToggleRoute']);
    }

    public function addMenuPage(): void
    {
        add_options_page(
            __('Reverse Proxy', 'reverse-proxy'),
            __('Reverse Proxy', 'reverse-proxy'),
            'manage_options',
            'reverse-proxy',
            [$this->routesPage, 'render']
        );
    }

    public function addPluginActionLinks(array $links): array
    {
        $settingsLink = sprintf(
            '<a href="%s">%s</a>',
            admin_url('options-general.php?page=reverse-proxy'),
            __('Settings')
        );

        array_unshift($links, $settingsLink);

        return $links;
    }

    public function enqueueAssets(string $hook): void
    {
        if ($hook !== 'settings_page_reverse-proxy') {
            return;
        }

        wp_enqueue_style(
            'reverse-proxy-admin',
            REVERSE_PROXY_PLUGIN_URL . 'assets/css/admin.css',
            [],
            REVERSE_PROXY_VERSION
        );

        // Enqueue CodeMirror for JSON editing
        $codeEditorSettings = wp_enqueue_code_editor(['type' => 'application/json']);

        wp_enqueue_script(
            'reverse-proxy-admin',
            REVERSE_PROXY_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery', 'jquery-ui-sortable', 'wp-i18n', 'wp-util'],
            REVERSE_PROXY_VERSION,
            true
        );

        wp_set_script_translations('reverse-proxy-admin', 'reverse-proxy', REVERSE_PROXY_PLUGIN_DIR . 'languages');

        // Get existing middlewares if editing a route
        $existingMiddlewares = [];
        if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['route_id'])) {
            $routeId = sanitize_text_field($_GET['route_id']);
            $route = $this->routesPage->getRouteById($routeId);
            if ($route !== null) {
                $existingMiddlewares = $route['middlewares'] ?? [];
            }
        }

        wp_localize_script('reverse-proxy-admin', 'reverseProxyAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'adminUrl' => admin_url('admin.php'),
            'nonce' => wp_create_nonce('reverse_proxy_admin'),
            'middlewares' => $this->routesPage->getAvailableMiddlewares(),
            'existingMiddlewares' => $existingMiddlewares,
            'codeEditor' => $codeEditorSettings,
        ]);
    }

    public function handleSaveRoute(): void
    {
        $this->verifyAjaxRequest();

        $route = isset($_POST['route']) ? (array) $_POST['route'] : [];
        $route['middlewares'] = $this->parseMiddlewaresJson();

        $result = $this->routesPage->saveRoute($route);

        if ($result) {
            wp_send_json_success([
                'message' => __('Route saved successfully.', 'reverse-proxy'),
                'redirect' => admin_url('options-general.php?page=reverse-proxy'),
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Failed to save route. Please check the path and target URL.', 'reverse-proxy'),
            ]);
        }
    }

    public function handleDeleteRoute(): void
    {
        $this->handleAjaxRouteAction(
            [$this->routesPage, 'deleteRoute'],
            __('Route deleted successfully.', 'reverse-proxy'),
            __('Failed to delete route.', 'reverse-proxy')
        );
    }

    public function handleToggleRoute(): void
    {
        $this->handleAjaxRouteAction(
            [$this->routesPage, 'toggleRoute'],
            __('Route status updated.', 'reverse-proxy'),
            __('Failed to update route status.', 'reverse-proxy')
        );
    }

    public function handleFormSubmission(): void
    {
        if (wp_doing_ajax()) {
            return;
        }

        // Handle save route form (POST)
        if (isset($_POST['action']) && sanitize_text_field($_POST['action']) === 'reverse_proxy_save_route') {
            $this->handleFormSaveRoute();

            return;
        }

        // Handle GET actions (delete, toggle)
        if (!$this->isReverseProxyPage() || !isset($_GET['action'], $_GET['route_id'], $_GET['_wpnonce'])) {
            return;
        }

        $action = sanitize_text_field($_GET['action']);

        if ($action === 'delete') {
            $this->handleFormDeleteRoute();
        } elseif ($action === 'toggle') {
            $this->handleFormToggleRoute();
        }
    }

    private function handleAjaxRouteAction(callable $action, string $successMessage, string $errorMessage): void
    {
        $this->verifyAjaxRequest();

        $routeId = isset($_POST['route_id']) ? sanitize_text_field($_POST['route_id']) : '';

        if (empty($routeId)) {
            wp_send_json_error(['message' => __('Route ID is required.', 'reverse-proxy')]);
        }

        $result = $action($routeId);

        if ($result) {
            wp_send_json_success(['message' => $successMessage]);
        } else {
            wp_send_json_error(['message' => $errorMessage]);
        }
    }

    private function isReverseProxyPage(): bool
    {
        return isset($_GET['page']) && $_GET['page'] === 'reverse-proxy';
    }

    private function handleFormSaveRoute(): void
    {
        $this->verifyFormRequest('reverse_proxy_nonce', 'reverse_proxy_save_route');

        $route = isset($_POST['route']) ? (array) $_POST['route'] : [];
        $route['middlewares'] = $this->parseMiddlewaresJson();

        $message = $this->routesPage->saveRoute($route) ? 'message=saved' : 'error=save_failed';
        $this->redirectToRoutesPage($message);
    }

    private function handleFormDeleteRoute(): void
    {
        $routeId = sanitize_text_field($_GET['route_id']);
        $this->verifyFormRequest('_wpnonce', 'delete_route_' . $routeId, 'GET');

        $this->routesPage->deleteRoute($routeId);
        $this->redirectToRoutesPage('message=deleted');
    }

    private function handleFormToggleRoute(): void
    {
        $routeId = sanitize_text_field($_GET['route_id']);
        $this->verifyFormRequest('_wpnonce', 'toggle_route_' . $routeId, 'GET');

        $this->routesPage->toggleRoute($routeId);
        $this->redirectToRoutesPage('message=toggled');
    }

    private function verifyFormRequest(string $nonceField, string $nonceAction, string $method = 'POST'): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Permission denied.', 'reverse-proxy'));
        }

        $source = $method === 'GET' ? $_GET : $_POST;
        $nonce = isset($source[$nonceField]) ? $source[$nonceField] : '';

        if (!wp_verify_nonce($nonce, $nonceAction)) {
            wp_die(__('Security check failed.', 'reverse-proxy'));
        }
    }

    private function redirectToRoutesPage(string $query = ''): void
    {
        $url = admin_url('options-general.php?page=reverse-proxy');
        if ($query) {
            $url .= '&' . $query;
        }
        wp_safe_redirect($url);
        exit;
    }

    private function verifyAjaxRequest(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'reverse-proxy')], 403);
        }

        if (!$this->verifyNonce('nonce', 'reverse_proxy_admin') &&
            !$this->verifyNonce('reverse_proxy_nonce', 'reverse_proxy_save_route')) {
            wp_send_json_error(['message' => __('Security check failed.', 'reverse-proxy')], 403);
        }
    }

    private function verifyNonce(string $field, string $action): bool
    {
        $nonce = isset($_POST[$field]) ? $_POST[$field] : '';

        return wp_verify_nonce($nonce, $action) !== false;
    }

    private function parseMiddlewaresJson(): array
    {
        if (empty($_POST['middlewares_json'])) {
            return [];
        }

        $json = stripslashes($_POST['middlewares_json']);
        $middlewares = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($middlewares)) {
            return [];
        }

        return $middlewares;
    }
}
