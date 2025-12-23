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

// Initialize plugin
add_action('parse_request', function ($wp) {
    $psr17Factory = new Nyholm\Psr7\Factory\Psr17Factory();

    // Create ServerRequest from globals
    $serverRequestFactory = new ReverseProxy\WordPress\ServerRequestFactory($psr17Factory);
    $request = $serverRequestFactory->createFromGlobals();

    // Get rules from filter
    $rules = apply_filters('reverse_proxy_rules', []);

    // Create dependencies
    $httpClient = apply_filters(
        'reverse_proxy_http_client',
        new ReverseProxy\Http\WordPressHttpClient()
    );
    $logger = new ReverseProxy\WordPress\Logger();

    // Create ReverseProxy
    $reverseProxy = new ReverseProxy\ReverseProxy(
        $httpClient,
        $psr17Factory,
        $psr17Factory,
        $logger
    );

    // Handle request
    $response = $reverseProxy->handle($request, $rules);

    if ($response !== null) {
        // Apply response filter
        $response = apply_filters('reverse_proxy_response', $response, $request);

        // Emit response
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
