<?php

/**
 * PHPUnit Bootstrap File for WordPress Plugin Testing
 *
 * Supports both local development and CI environments
 */

// Load Composer autoloader
require_once dirname(__DIR__).'/vendor/autoload.php';

// Load WordPress test framework functions
require_once dirname(__DIR__).'/vendor/wp-phpunit/wp-phpunit/includes/functions.php';

/**
 * Manually load plugins before WordPress initializes
 */
function _manually_load_plugins()
{
    // Load our plugin
    require dirname(__DIR__).'/reverse-proxy.php';
}

// Register plugin loader hook
tests_add_filter('muplugins_loaded', '_manually_load_plugins');

// Start up the WordPress testing environment
require dirname(__DIR__).'/vendor/wp-phpunit/wp-phpunit/includes/bootstrap.php';
