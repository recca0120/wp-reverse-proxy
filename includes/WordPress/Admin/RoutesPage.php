<?php

namespace Recca0120\ReverseProxy\WordPress\Admin;

use Recca0120\ReverseProxy\Contracts\StorageInterface;
use Recca0120\ReverseProxy\Routing\MiddlewareRegistry;
use Recca0120\ReverseProxy\Support\Arr;

class RoutesPage
{
    private const VALID_METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'];

    /** @var MiddlewareRegistry|null */
    private $registry;

    /** @var StorageInterface */
    private $storage;

    public function __construct(?MiddlewareRegistry $registry = null, ?StorageInterface $storage = null)
    {
        $this->registry = $registry;
        $this->storage = $storage ?? new OptionsStorage();
    }

    public function getStorage(): StorageInterface
    {
        return $this->storage;
    }

    /**
     * Get available middlewares from the registry.
     *
     * @return array<string, array>
     */
    public function getAvailableMiddlewares(): array
    {
        return $this->registry !== null ? $this->registry->getAvailable() : [];
    }

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
        return $this->storage->getAll();
    }

    public function getRouteById(string $id): ?array
    {
        $routes = $this->getRoutes();
        $index = $this->findRouteIndex($routes, $id);

        return $index !== null ? $routes[$index] : null;
    }

    public function getActionUrl(string $routeId, string $action): string
    {
        return wp_nonce_url(
            admin_url("options-general.php?page=reverse-proxy&action={$action}&route_id={$routeId}"),
            "{$action}_route_{$routeId}"
        );
    }

    public function saveRoute(array $route): bool
    {
        $sanitized = $this->sanitizeRoute($route);

        if (empty($sanitized['path']) || empty($sanitized['target'])) {
            return false;
        }

        $routes = $this->getRoutes();

        if (empty($sanitized['id'])) {
            $sanitized['id'] = 'route_' . wp_generate_uuid4();
            $routes[] = $sanitized;
        } else {
            $index = $this->findRouteIndex($routes, $sanitized['id']);
            if ($index !== null) {
                $routes[$index] = $sanitized;
            } else {
                $routes[] = $sanitized;
            }
        }

        return $this->saveRoutes($routes);
    }

    public function deleteRoute(string $id): bool
    {
        $routes = $this->getRoutes();
        $routes = array_values(array_filter($routes, function ($route) use ($id) {
            return $route['id'] !== $id;
        }));

        return $this->saveRoutes($routes);
    }

    public function toggleRoute(string $id): bool
    {
        $routes = $this->getRoutes();
        $index = $this->findRouteIndex($routes, $id);

        if ($index === null) {
            return false;
        }

        $routes[$index]['enabled'] = !$routes[$index]['enabled'];

        return $this->saveRoutes($routes);
    }

    public function getVersion(): int
    {
        return $this->storage->getVersion();
    }

    public function sanitizeRoute(array $input): array
    {
        return [
            'id' => isset($input['id']) ? sanitize_text_field($input['id']) : '',
            'path' => isset($input['path']) ? sanitize_text_field($input['path']) : '',
            'target' => $this->sanitizeTargetUrl($input['target'] ?? ''),
            'methods' => $this->sanitizeMethods($input['methods'] ?? []),
            'middlewares' => $this->sanitizeMiddlewares($input['middlewares'] ?? []),
            'enabled' => filter_var($input['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
    }

    private function sanitizeTargetUrl(string $target): string
    {
        if (empty($target)) {
            return '';
        }

        $target = esc_url_raw($target);
        $parsed = parse_url($target);

        if (
            !isset($parsed['scheme']) ||
            !Arr::contains(['http', 'https'], $parsed['scheme']) ||
            empty($parsed['host']) ||
            !$this->isValidHost($parsed['host'])
        ) {
            return '';
        }

        return $target;
    }

    private function sanitizeMethods(array $methods): array
    {
        if (empty($methods)) {
            return [];
        }

        return array_values(array_filter(
            array_map('strtoupper', $methods),
            function ($method) {
                return Arr::contains(self::VALID_METHODS, $method);
            }
        ));
    }

    private function findRouteIndex(array $routes, string $id): ?int
    {
        foreach ($routes as $index => $route) {
            if ($route['id'] === $id) {
                return $index;
            }
        }

        return null;
    }

    private function saveRoutes(array $routes): bool
    {
        return $this->storage->save($routes);
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
        $routesPage = $this;
        include REVERSE_PROXY_PLUGIN_DIR . 'templates/admin/routes.php';
    }

    private function renderEditPage(?string $routeId): void
    {
        $route = $routeId ? $this->getRouteById($routeId) : null;
        $middlewares = $this->getAvailableMiddlewares();
        include REVERSE_PROXY_PLUGIN_DIR . 'templates/admin/route-form.php';
    }
}
