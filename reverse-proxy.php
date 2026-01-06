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

// Load Composer autoloader (prefer vendor-prefixed for release builds)
if (file_exists(REVERSE_PROXY_PLUGIN_DIR.'vendor-prefixed/autoload.php')) {
    require_once REVERSE_PROXY_PLUGIN_DIR.'vendor-prefixed/autoload.php';
}
if (file_exists(REVERSE_PROXY_PLUGIN_DIR.'vendor/autoload.php')) {
    require_once REVERSE_PROXY_PLUGIN_DIR.'vendor/autoload.php';
}

function reverse_proxy_create_proxy()
{
    $psr17Factory = apply_filters('reverse_proxy_psr17_factory', new Nyholm\Psr7\Factory\Psr17Factory);
    $httpClient = new ReverseProxy\Http\FilteringClient(
        apply_filters('reverse_proxy_http_client', new ReverseProxy\Http\CurlClient(['verify' => false, 'decode_content' => false]))
    );

    $proxy = new ReverseProxy\ReverseProxy($httpClient, $psr17Factory, $psr17Factory);
    $proxy->addGlobalMiddlewares(apply_filters('reverse_proxy_default_middlewares', [
        new ReverseProxy\Middleware\ErrorHandling,
        new ReverseProxy\Middleware\Logging(new ReverseProxy\WordPress\Logger),
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

    return (new ReverseProxy\Http\ServerRequestFactory($psr17Factory))->createFromGlobals();
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

function reverse_proxy_handle()
{
    $proxy = reverse_proxy_create_proxy();
    $request = reverse_proxy_create_request();
    $routes = apply_filters('reverse_proxy_routes', []);

    try {
        $response = $proxy->handle($request, $routes);

        if ($response !== null) {
            reverse_proxy_send_response($response);
        }
    } catch (ReverseProxy\Exceptions\FallbackException $e) {
        // Let WordPress handle the request
        return;
    }
}

add_action(
    apply_filters('reverse_proxy_action_hook', 'plugins_loaded'),
    'reverse_proxy_handle'
);
