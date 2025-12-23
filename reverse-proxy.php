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
    $reverseProxy = new ReverseProxy\ReverseProxy();
    if ($reverseProxy->handle($wp) && apply_filters('reverse_proxy_should_exit', true)) {
        exit;
    }
});
