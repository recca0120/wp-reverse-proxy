<?php

/**
 * Plugin Name: Reverse Proxy
 * Plugin URI: https://github.com/recca0120/wp-reverse-proxy
 * Description: Proxy requests to backend servers with configurable routes and middleware support.
 * Version: 0.3.11
 * Author: Recca Tsai
 * Author URI: https://github.com/recca0120
 * License: MIT
 * Text Domain: reverse-proxy
 */
if (! defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('REVERSE_PROXY_VERSION', '1.0.0');
define('REVERSE_PROXY_PLUGIN_FILE', __FILE__);
define('REVERSE_PROXY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('REVERSE_PROXY_PLUGIN_URL', plugin_dir_url(__FILE__));

// Load Composer autoloader
if (file_exists(REVERSE_PROXY_PLUGIN_DIR.'vendor-prefixed/autoload.php')) {
    // Production: load prefixed vendor dependencies (includes Recca0120\ReverseProxy\ namespace)
    require_once REVERSE_PROXY_PLUGIN_DIR.'vendor-prefixed/autoload.php';
} elseif (file_exists(REVERSE_PROXY_PLUGIN_DIR.'vendor/autoload.php')) {
    // Development: load original vendor dependencies
    require_once REVERSE_PROXY_PLUGIN_DIR.'vendor/autoload.php';
}

function reverse_proxy_handle()
{
    // Load routes from config files (JSON/PHP)
    $configRoutes = reverse_proxy_load_config_routes();

    // Merge with routes from filter hook (for programmatic routes)
    $routes = apply_filters('reverse_proxy_routes', $configRoutes);

    $proxy = reverse_proxy_create_proxy($routes);
    $request = apply_filters('reverse_proxy_request', null);

    try {
        $response = $proxy->handle($request);

        if ($response !== null) {
            reverse_proxy_send_response($response);
        }
    } catch (Recca0120\ReverseProxy\Exceptions\FallbackException $e) {
        // Let WordPress handle the request
        return;
    }
}

function reverse_proxy_load_config_routes()
{
    $routes = apply_filters('reverse_proxy_route_collection', null);

    if ($routes === null) {
        $directory = apply_filters('reverse_proxy_routes_directory', WP_CONTENT_DIR.'/reverse-proxy-routes');

        $cache = apply_filters(
            'reverse_proxy_cache',
            new Recca0120\ReverseProxy\WordPress\TransientCache()
        );

        $middlewareManager = reverse_proxy_create_middleware_manager();

        $storage = reverse_proxy_create_route_storage();

        $loaders = apply_filters('reverse_proxy_route_loaders', [
            new Recca0120\ReverseProxy\Routing\FileLoader([$directory]),
            new Recca0120\ReverseProxy\WordPress\WordPressLoader($storage),
        ]);

        $routes = new Recca0120\ReverseProxy\Routing\RouteCollection(
            $loaders,
            $cache,
            $middlewareManager
        );
    }

    return $routes;
}

function reverse_proxy_create_route_storage()
{
    return apply_filters(
        'reverse_proxy_route_storage',
        new Recca0120\ReverseProxy\WordPress\Admin\OptionsStorage()
    );
}

function reverse_proxy_create_middleware_manager()
{
    $manager = apply_filters(
        'reverse_proxy_middleware_manager',
        new Recca0120\ReverseProxy\Routing\MiddlewareManager()
    );

    $manager->registerGlobalMiddleware(
        apply_filters('reverse_proxy_global_middlewares', [
            new Recca0120\ReverseProxy\Middleware\SanitizeHeaders(),
            new Recca0120\ReverseProxy\Middleware\ErrorHandling(),
            new Recca0120\ReverseProxy\Middleware\Logging(new Recca0120\ReverseProxy\WordPress\Logger()),
        ])
    );

    return $manager;
}

function reverse_proxy_create_proxy(Recca0120\ReverseProxy\Routing\RouteCollection $routes)
{
    $httpClient = apply_filters('reverse_proxy_http_client', null);

    return new Recca0120\ReverseProxy\ReverseProxy($routes, $httpClient);
}

function reverse_proxy_send_response($response)
{
    $response = apply_filters('reverse_proxy_response', $response);

    if (! headers_sent()) {
        http_response_code($response->getStatusCode());
        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                header("{$name}: {$value}", false);
            }
        }
    }
    echo $response->getBody();

    if (apply_filters('reverse_proxy_should_exit', true)) {
        exit;
    }
}

add_action(
    apply_filters('reverse_proxy_action_hook', 'plugins_loaded'),
    'reverse_proxy_handle'
);

// Load text domain for translations (must be on 'init' hook per WordPress 6.7+)
add_action('init', function () {
    load_plugin_textdomain('reverse-proxy', false, dirname(plugin_basename(REVERSE_PROXY_PLUGIN_FILE)) . '/languages');
});

// Initialize Admin interface (only in admin area)
if (is_admin()) {
    add_action('plugins_loaded', function () {
        $middlewareManager = reverse_proxy_create_middleware_manager();
        $registry = new Recca0120\ReverseProxy\Routing\MiddlewareRegistry($middlewareManager);
        $storage = reverse_proxy_create_route_storage();
        $routesPage = new Recca0120\ReverseProxy\WordPress\Admin\RoutesPage($registry, $storage);
        $admin = new Recca0120\ReverseProxy\WordPress\Admin\Admin($routesPage);
        $admin->register();
    });
}
