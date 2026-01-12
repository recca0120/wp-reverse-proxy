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
        add_action('wp_ajax_reverse_proxy_export_routes', [$this, 'handleExportRoutes']);
        add_action('wp_ajax_reverse_proxy_import_routes', [$this, 'handleImportRoutes']);
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

        // Register SortableJS (no jQuery dependency)
        wp_register_script(
            'sortablejs',
            REVERSE_PROXY_PLUGIN_URL . 'assets/js/sortable.min.js',
            [],
            '1.15.6',
            true
        );

        wp_enqueue_script(
            'reverse-proxy-admin',
            REVERSE_PROXY_PLUGIN_URL . 'assets/js/admin.js',
            ['sortablejs'],
            REVERSE_PROXY_VERSION,
            true
        );

        // Get existing middlewares if editing a route
        $existingMiddlewares = [];
        if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['route_id'])) {
            $routeId = sanitize_text_field($_GET['route_id']);
            $route = $this->routesPage->findRoute($routeId);
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
            'i18n' => [
                'confirmDelete' => __('Are you sure you want to delete this route?', 'reverse-proxy'),
                'error' => __('An error occurred.', 'reverse-proxy'),
                'toggleFailed' => __('Failed to toggle route status.', 'reverse-proxy'),
                'deleteFailed' => __('Failed to delete route.', 'reverse-proxy'),
                'saving' => __('Saving...', 'reverse-proxy'),
                'saveFailed' => __('Failed to save route.', 'reverse-proxy'),
                'saveError' => __('An error occurred while saving.', 'reverse-proxy'),
                'exporting' => __('Exporting...', 'reverse-proxy'),
                'export' => __('Export', 'reverse-proxy'),
                'exportFailed' => __('Export failed.', 'reverse-proxy'),
                'importing' => __('Importing...', 'reverse-proxy'),
                'import' => __('Import', 'reverse-proxy'),
                'importFailed' => __('Import failed.', 'reverse-proxy'),
                'invalidJson' => __('Invalid JSON file.', 'reverse-proxy'),
                'importRoutes' => __('Import %d routes?', 'reverse-proxy'),
                'chooseMode' => __('Choose import mode:', 'reverse-proxy'),
                'mergeMode' => __('Merge: Add new routes, update existing by ID', 'reverse-proxy'),
                'replaceMode' => __('Replace: Remove all existing routes first', 'reverse-proxy'),
                'enterMode' => __('Enter "merge" or "replace":', 'reverse-proxy'),
                'invalidMode' => __('Invalid mode. Please enter "merge" or "replace".', 'reverse-proxy'),
            ],
        ]);
    }

    public function handleSaveRoute(): void
    {
        $this->verifyAjaxRequest();

        $result = $this->routesPage->saveRoute($this->getRouteFromRequest());

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

    public function handleExportRoutes(): void
    {
        $this->verifyAjaxRequest('GET');

        wp_send_json_success($this->routesPage->exportRoutes());
    }

    public function handleImportRoutes(): void
    {
        $this->verifyAjaxRequest();

        $jsonData = isset($_POST['data']) ? stripslashes($_POST['data']) : '';
        $mode = isset($_POST['mode']) ? sanitize_text_field($_POST['mode']) : 'merge';

        $data = json_decode($jsonData, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(['message' => __('Invalid JSON data.', 'reverse-proxy')]);
        }

        $result = $this->routesPage->importRoutes($data, $mode);

        if ($result['success']) {
            wp_send_json_success([
                'message' => sprintf(
                    __('Import completed. %d routes imported, %d skipped.', 'reverse-proxy'),
                    $result['imported'],
                    $result['skipped']
                ),
                'imported' => $result['imported'],
                'skipped' => $result['skipped'],
            ]);
        } else {
            wp_send_json_error(['message' => $result['error'] ?? __('Import failed.', 'reverse-proxy')]);
        }
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

        $message = $this->routesPage->saveRoute($this->getRouteFromRequest()) ? 'message=saved' : 'error=save_failed';
        $this->redirectToRoutesPage($message);
    }

    private function handleFormDeleteRoute(): void
    {
        $this->handleFormRouteAction('delete', 'deleteRoute', 'deleted');
    }

    private function handleFormToggleRoute(): void
    {
        $this->handleFormRouteAction('toggle', 'toggleRoute', 'toggled');
    }

    private function handleFormRouteAction(string $action, string $method, string $message): void
    {
        $routeId = sanitize_text_field($_GET['route_id']);
        $this->verifyFormRequest('_wpnonce', $action . '_route_' . $routeId, 'GET');

        $this->routesPage->$method($routeId);
        $this->redirectToRoutesPage('message=' . $message);
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

    private function verifyAjaxRequest(string $method = 'POST'): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'reverse-proxy')], 403);
        }

        $source = $method === 'GET' ? $_GET : $_POST;

        if (!$this->verifyNonce('nonce', 'reverse_proxy_admin', $source) &&
            !$this->verifyNonce('reverse_proxy_nonce', 'reverse_proxy_save_route', $source)) {
            wp_send_json_error(['message' => __('Security check failed.', 'reverse-proxy')], 403);
        }
    }

    private function verifyNonce(string $field, string $action, ?array $source = null): bool
    {
        $source = $source ?? $_POST;
        $nonce = $source[$field] ?? '';

        return wp_verify_nonce($nonce, $action) !== false;
    }

    private function getRouteFromRequest(): array
    {
        $route = isset($_POST['route']) ? (array) $_POST['route'] : [];
        $route['middlewares'] = $this->parseMiddlewaresJson();

        return $route;
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
