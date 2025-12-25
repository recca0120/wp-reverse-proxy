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
if (file_exists(REVERSE_PROXY_PLUGIN_DIR.'vendor/autoload.php')) {
    require_once REVERSE_PROXY_PLUGIN_DIR.'vendor/autoload.php';
}

// Handle proxy requests
add_action('parse_request', function () {
    $psr17Factory = apply_filters('reverse_proxy_psr17_factory', new Nyholm\Psr7\Factory\Psr17Factory);
    $httpClient = new ReverseProxy\Http\FilteringHttpClient(
        apply_filters('reverse_proxy_http_client', new ReverseProxy\Http\WordPressHttpClient)
    );

    $proxy = new ReverseProxy\ReverseProxy($httpClient, $psr17Factory, $psr17Factory);
    $proxy->addGlobalMiddlewares(apply_filters('reverse_proxy_default_middlewares', [
        new ReverseProxy\Middleware\ErrorHandlingMiddleware,
        new ReverseProxy\Middleware\LoggingMiddleware(new ReverseProxy\WordPress\Logger),
    ]));

    $serverRequestFactory = new ReverseProxy\WordPress\ServerRequestFactory($psr17Factory);

    $routes = apply_filters('reverse_proxy_routes', []);
    $request = $serverRequestFactory->createFromGlobals();
    $response = $proxy->handle($request, $routes);

    if ($response !== null) {
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
});
