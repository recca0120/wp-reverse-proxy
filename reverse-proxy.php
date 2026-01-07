<?php

/**
 * Plugin Name: Reverse Proxy
 * Plugin URI: https://github.com/recca0120/wp-reverse-proxy
 * Description: WordPress reverse proxy plugin
 * Version: 1.0.0
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

function reverse_proxy_create_proxy()
{
    $psr17Factory = apply_filters('reverse_proxy_psr17_factory', new Nyholm\Psr7\Factory\Psr17Factory);
    $httpClient = apply_filters('reverse_proxy_http_client', new Recca0120\ReverseProxy\Http\CurlClient(['verify' => false, 'decode_content' => false]));

    $proxy = new Recca0120\ReverseProxy\ReverseProxy($httpClient, $psr17Factory, $psr17Factory);
    $proxy->addGlobalMiddlewares(apply_filters('reverse_proxy_default_middlewares', [
        new Recca0120\ReverseProxy\Middleware\SanitizeHeaders,
        new Recca0120\ReverseProxy\Middleware\ErrorHandling,
        new Recca0120\ReverseProxy\Middleware\Logging(new Recca0120\ReverseProxy\WordPress\Logger),
    ]));

    return $proxy;
}

function reverse_proxy_create_request()
{
    $request = apply_filters('reverse_proxy_request', null);
    if ($request !== null) {
        return $request;
    }

    $psr17Factory = apply_filters('reverse_proxy_psr17_factory', new Nyholm\Psr7\Factory\Psr17Factory);

    return (new Recca0120\ReverseProxy\Http\ServerRequestFactory($psr17Factory))->createFromGlobals();
}

function reverse_proxy_emit_response($response)
{
    if (! headers_sent()) {
        http_response_code($response->getStatusCode());
        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                header("{$name}: {$value}", false);
            }
        }
    }
    echo $response->getBody();
}

function reverse_proxy_send_response($response)
{
    $response = apply_filters('reverse_proxy_response', $response);
    reverse_proxy_emit_response($response);

    if (apply_filters('reverse_proxy_should_exit', true)) {
        exit;
    }
}

function reverse_proxy_get_config_loader()
{
    static $configLoader = null;

    if ($configLoader === null) {
        $configLoader = apply_filters('reverse_proxy_config_loader', null);
    }

    if ($configLoader === null) {
        $configLoader = new Recca0120\ReverseProxy\Config\ConfigLoader(
            [
                new Recca0120\ReverseProxy\Config\Loaders\JsonLoader,
                new Recca0120\ReverseProxy\Config\Loaders\PhpArrayLoader,
            ],
            new Recca0120\ReverseProxy\Config\MiddlewareFactory,
            apply_filters('reverse_proxy_config_cache', null)
        );
    }

    return $configLoader;
}

function reverse_proxy_load_config_routes()
{
    $configLoader = reverse_proxy_get_config_loader();
    $directory = apply_filters('reverse_proxy_config_directory', WPMU_PLUGIN_DIR);
    $pattern = apply_filters('reverse_proxy_config_pattern', '*.routes.*');

    return $configLoader->loadFromDirectory($directory, $pattern);
}

function reverse_proxy_handle()
{
    $proxy = reverse_proxy_create_proxy();
    $request = reverse_proxy_create_request();

    // Load routes from config files (JSON/PHP)
    $configRoutes = reverse_proxy_load_config_routes();

    // Merge with routes from filter hook (for programmatic routes)
    $routes = apply_filters('reverse_proxy_routes', $configRoutes);

    try {
        $response = $proxy->handle($request, $routes);

        if ($response !== null) {
            reverse_proxy_send_response($response);
        }
    } catch (Recca0120\ReverseProxy\Exceptions\FallbackException $e) {
        // Let WordPress handle the request
        return;
    }
}

add_action(
    apply_filters('reverse_proxy_action_hook', 'plugins_loaded'),
    'reverse_proxy_handle'
);
