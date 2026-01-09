<?php

namespace Recca0120\ReverseProxy\WordPress\Admin;

use Recca0120\ReverseProxy\Routing\MiddlewareFactory;
use Recca0120\ReverseProxy\Routing\Route;

class RoutesPage
{
    public const OPTION_NAME = 'reverse_proxy_admin_routes';

    public const VERSION_OPTION_NAME = 'reverse_proxy_admin_routes_version';

    private const VALID_METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'];

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        $routeId = isset($_GET['route_id']) ? sanitize_text_field($_GET['route_id']) : null;

        switch ($action) {
            case 'edit':
            case 'new':
                $this->renderEditPage($routeId);
                break;
            default:
                $this->renderListPage();
                break;
        }
    }

    public function getRoutes(): array
    {
        return get_option(self::OPTION_NAME, []);
    }

    public function saveRoute(array $route): bool
    {
        $sanitized = $this->sanitizeRoute($route);

        // Validate required fields
        if (empty($sanitized['path'])) {
            return false;
        }

        if (empty($sanitized['target'])) {
            return false;
        }

        $routes = $this->getRoutes();

        // Generate ID if not provided
        if (empty($sanitized['id'])) {
            $sanitized['id'] = 'route_' . wp_generate_uuid4();
            $routes[] = $sanitized;
        } else {
            // Update existing route
            $found = false;
            foreach ($routes as $index => $existingRoute) {
                if ($existingRoute['id'] === $sanitized['id']) {
                    $routes[$index] = $sanitized;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $routes[] = $sanitized;
            }
        }

        $result = update_option(self::OPTION_NAME, $routes);

        if ($result) {
            $this->incrementVersion();
        }

        return $result;
    }

    public function deleteRoute(string $id): bool
    {
        $routes = $this->getRoutes();
        $routes = array_filter($routes, function ($route) use ($id) {
            return $route['id'] !== $id;
        });

        $result = update_option(self::OPTION_NAME, array_values($routes));

        if ($result) {
            $this->incrementVersion();
        }

        return $result;
    }

    public function toggleRoute(string $id): bool
    {
        $routes = $this->getRoutes();

        foreach ($routes as $index => $route) {
            if ($route['id'] === $id) {
                $routes[$index]['enabled'] = !$route['enabled'];
                break;
            }
        }

        $result = update_option(self::OPTION_NAME, $routes);

        if ($result) {
            $this->incrementVersion();
        }

        return $result;
    }

    public static function getVersion(): int
    {
        return (int) get_option(self::VERSION_OPTION_NAME, 0);
    }

    public function sanitizeRoute(array $input): array
    {
        $sanitized = [
            'id' => isset($input['id']) ? sanitize_text_field($input['id']) : '',
            'path' => isset($input['path']) ? sanitize_text_field($input['path']) : '',
            'target' => '',
            'methods' => [],
            'middlewares' => [],
            'enabled' => false,
        ];

        // Sanitize target URL
        if (!empty($input['target'])) {
            $target = esc_url_raw($input['target']);
            // Validate it's a proper HTTP(S) URL with a valid host
            $parsed = parse_url($target);
            if (
                isset($parsed['scheme']) &&
                in_array($parsed['scheme'], ['http', 'https'], true) &&
                !empty($parsed['host']) &&
                $this->isValidHost($parsed['host'])
            ) {
                $sanitized['target'] = $target;
            }
        }

        // Sanitize methods
        if (!empty($input['methods']) && is_array($input['methods'])) {
            $sanitized['methods'] = array_values(array_filter(
                array_map('strtoupper', $input['methods']),
                function ($method) {
                    return in_array($method, self::VALID_METHODS, true);
                }
            ));
        }

        // Sanitize middlewares
        if (!empty($input['middlewares']) && is_array($input['middlewares'])) {
            $sanitized['middlewares'] = $this->sanitizeMiddlewares($input['middlewares']);
        }

        // Sanitize enabled
        if (isset($input['enabled'])) {
            $sanitized['enabled'] = filter_var($input['enabled'], FILTER_VALIDATE_BOOLEAN);
        }

        return $sanitized;
    }

    public static function toRouteObject(array $data): Route
    {
        $path = $data['path'] ?? '';
        $target = $data['target'] ?? '';
        $methods = $data['methods'] ?? [];
        $middlewaresConfig = $data['middlewares'] ?? [];

        // Prepend methods to path if specified
        if (!empty($methods)) {
            $path = implode('|', $methods) . ' ' . $path;
        }

        // Create middleware instances
        $factory = new MiddlewareFactory();
        $middlewares = $factory->createMany($middlewaresConfig);

        return new Route($path, $target, $middlewares);
    }

    private function incrementVersion(): void
    {
        $version = self::getVersion();
        update_option(self::VERSION_OPTION_NAME, $version + 1);
    }

    private function sanitizeMiddlewares(array $middlewares): array
    {
        $sanitized = [];

        foreach ($middlewares as $middleware) {
            if (is_string($middleware)) {
                $sanitized[] = sanitize_text_field($middleware);
            } elseif (is_array($middleware)) {
                // Array format: ['MiddlewareName', 'arg1', 'arg2'] or ['name' => '...', 'options' => [...]]
                $sanitized[] = $this->sanitizeMiddlewareArray($middleware);
            }
        }

        return $sanitized;
    }

    private function sanitizeMiddlewareArray(array $middleware): array
    {
        $sanitized = [];

        foreach ($middleware as $key => $value) {
            if (is_string($key)) {
                $sanitized[sanitize_text_field($key)] = $this->sanitizeMiddlewareValue($value);
            } else {
                $sanitized[] = $this->sanitizeMiddlewareValue($value);
            }
        }

        return $sanitized;
    }

    private function sanitizeMiddlewareValue($value)
    {
        if (is_string($value)) {
            return sanitize_text_field($value);
        }

        if (is_numeric($value)) {
            return $value;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_array($value)) {
            return $this->sanitizeMiddlewareArray($value);
        }

        return null;
    }

    private function isValidHost(string $host): bool
    {
        // Allow localhost
        if ($host === 'localhost') {
            return true;
        }

        // Allow IP addresses (IPv4 and IPv6)
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return true;
        }

        // Require at least one dot for domain names (e.g., example.com)
        // This prevents single-word hosts like 'not-a-valid-url'
        if (strpos($host, '.') === false) {
            return false;
        }

        // Basic domain validation
        return (bool) preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9-]*[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9-]*[a-zA-Z0-9])?)*$/', $host);
    }

    private function renderListPage(): void
    {
        $routes = $this->getRoutes();
        include REVERSE_PROXY_PLUGIN_DIR . 'templates/routes-list.php';
    }

    private function renderEditPage(?string $routeId): void
    {
        $route = null;
        if ($routeId) {
            $routes = $this->getRoutes();
            foreach ($routes as $r) {
                if ($r['id'] === $routeId) {
                    $route = $r;
                    break;
                }
            }
        }

        $middlewares = MiddlewareRegistry::getAll();
        include REVERSE_PROXY_PLUGIN_DIR . 'templates/route-edit.php';
    }
}
