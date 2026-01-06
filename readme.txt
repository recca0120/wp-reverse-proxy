=== Reverse Proxy ===
Contributors: raborecca
Tags: proxy, reverse-proxy, api, gateway, middleware
Requires at least: 5.0
Tested up to: 6.7
Requires PHP: 7.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A WordPress plugin that proxies specific URL paths to external backend servers with middleware support.

== Description ==

Reverse Proxy is a powerful WordPress plugin that allows you to proxy specific URL paths to external backend servers. It's perfect for integrating external APIs, microservices, or legacy systems into your WordPress site.

= Features =

* Route matching with wildcard support (`/api/*`)
* HTTP method matching (`POST /api/users`, `GET|POST /api/*`)
* Path rewriting via middleware
* Full HTTP method support (GET, POST, PUT, PATCH, DELETE)
* Request/Response headers forwarding
* Query string preservation
* Customizable Host header
* Standard proxy headers (X-Real-IP, X-Forwarded-For, etc.)
* Error handling with 502 Bad Gateway
* Logging integration
* PSR-18 compliant HTTP client
* Middleware support for request/response processing

= Built-in Middlewares =

* **ProxyHeaders** - Adds standard proxy headers (X-Real-IP, X-Forwarded-For, etc.)
* **SetHost** - Sets a custom Host header
* **RewritePath** - Rewrites the request path
* **RewriteBody** - Rewrites response body using regex
* **AllowMethods** - Restricts allowed HTTP methods
* **Cors** - Handles Cross-Origin Resource Sharing
* **RequestId** - Generates or propagates request tracking ID
* **IpFilter** - IP whitelist/blacklist filtering
* **RateLimiting** - Request rate limiting
* **Caching** - Response caching using WordPress transients
* **Retry** - Automatic retry on failure
* **CircuitBreaker** - Circuit breaker pattern for fault tolerance
* **Timeout** - Request timeout control
* **Fallback** - Fall back to WordPress on specific status codes
* **ErrorHandling** - Catches exceptions and returns 502
* **Logging** - Logs proxy requests and responses

= Use Cases =

* Integrate external REST APIs into your WordPress site
* Proxy requests to microservices
* Add authentication headers to API requests
* Implement rate limiting for external services
* Cache API responses
* Handle CORS for cross-origin requests

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/reverse-proxy` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Configure your proxy routes using the `reverse_proxy_routes` filter.

= Basic Configuration =

Create a configuration file at `wp-content/mu-plugins/reverse-proxy-config.php`:

`<?php
/**
 * Plugin Name: Reverse Proxy Config
 * Description: Custom reverse proxy routes configuration
 */

use ReverseProxy\Route;

add_filter('reverse_proxy_routes', function () {
    return [
        new Route('/api/*', 'https://api.example.com'),
    ];
});`

== Frequently Asked Questions ==

= Why use mu-plugins for configuration? =

Using mu-plugins ensures your configuration:
* Auto-loads without requiring activation
* Is theme-independent
* Loads before regular plugins
* Is ideal for infrastructure-level configuration

= Can I use multiple routes? =

Yes! Routes are matched in order (first match wins):

`add_filter('reverse_proxy_routes', function () {
    return [
        new Route('/api/v2/*', 'https://api-v2.example.com'),
        new Route('/api/*', 'https://api.example.com'),
    ];
});`

= How do I add proxy headers? =

Use the ProxyHeaders middleware:

`new Route('/api/*', 'https://backend.example.com', [
    new \ReverseProxy\Middleware\ProxyHeaders(),
]);`

= Can I restrict HTTP methods? =

Yes, use HTTP method prefixes:

`new Route('POST /api/users', 'https://backend.example.com');
new Route('GET|POST /api/*', 'https://backend.example.com');`

= How does caching work? =

Use the Caching middleware to cache responses:

`new Route('/api/*', 'https://backend.example.com', [
    new \ReverseProxy\Middleware\Caching(300), // Cache for 5 minutes
]);`

== Screenshots ==

1. No admin interface required - configure via code for maximum flexibility and security.

== Changelog ==

= 1.0.0 =
* Initial release
* Route matching with wildcard support
* HTTP method matching
* 16 built-in middlewares
* PSR-18 compliant HTTP client
* Full test coverage

== Upgrade Notice ==

= 1.0.0 =
Initial release of Reverse Proxy plugin.

== Developer Documentation ==

= Available Hooks =

**Filters:**

* `reverse_proxy_routes` - Configure proxy routes
* `reverse_proxy_default_middlewares` - Customize default middlewares
* `reverse_proxy_psr17_factory` - Override PSR-17 HTTP factory
* `reverse_proxy_http_client` - Override PSR-18 HTTP client
* `reverse_proxy_request` - Override the request object
* `reverse_proxy_response` - Modify response before sending
* `reverse_proxy_action_hook` - Set the trigger action hook
* `reverse_proxy_should_exit` - Control exit behavior

**Actions:**

* `reverse_proxy_log` - Log proxy events (PSR-3 levels)

= Creating Custom Middleware =

`use ReverseProxy\Contracts\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

class MyMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        // Modify request
        $request = $request->withHeader('X-Custom', 'value');

        // Call next middleware
        $response = $next($request);

        // Modify response
        return $response->withHeader('X-Processed', 'true');
    }
}`

For more information, visit the [GitHub repository](https://github.com/recca0120/wp-reverse-proxy).
