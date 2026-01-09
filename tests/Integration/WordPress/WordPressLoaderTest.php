<?php

namespace Recca0120\ReverseProxy\Tests\Integration\WordPress;

use WP_UnitTestCase;
use Recca0120\ReverseProxy\Contracts\RouteLoaderInterface;
use Recca0120\ReverseProxy\WordPress\WordPressLoader;
use Recca0120\ReverseProxy\WordPress\Admin\RoutesPage;

class WordPressLoaderTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        delete_option(RoutesPage::OPTION_NAME);
        delete_option(RoutesPage::VERSION_OPTION_NAME);
    }

    protected function tearDown(): void
    {
        delete_option(RoutesPage::OPTION_NAME);
        delete_option(RoutesPage::VERSION_OPTION_NAME);
        parent::tearDown();
    }

    public function test_it_implements_route_loader_interface(): void
    {
        $loader = new WordPressLoader();

        $this->assertInstanceOf(RouteLoaderInterface::class, $loader);
    }

    public function test_load_returns_empty_array_when_no_routes(): void
    {
        $loader = new WordPressLoader();

        $routes = $loader->load();

        $this->assertIsArray($routes);
        $this->assertEmpty($routes);
    }

    public function test_load_returns_only_enabled_routes(): void
    {
        update_option(RoutesPage::OPTION_NAME, [
            [
                'id' => 'route_1',
                'enabled' => true,
                'path' => '/api/*',
                'target' => 'https://api.example.com',
                'methods' => [],
                'middlewares' => [],
            ],
            [
                'id' => 'route_2',
                'enabled' => false,
                'path' => '/disabled/*',
                'target' => 'https://disabled.example.com',
                'methods' => [],
                'middlewares' => [],
            ],
            [
                'id' => 'route_3',
                'enabled' => true,
                'path' => '/web/*',
                'target' => 'https://web.example.com',
                'methods' => [],
                'middlewares' => [],
            ],
        ]);

        $loader = new WordPressLoader();
        $routes = $loader->load();

        $this->assertCount(2, $routes);
        $this->assertEquals('/api/*', $routes[0]['path']);
        $this->assertEquals('/web/*', $routes[1]['path']);
    }

    public function test_load_converts_methods_to_path_prefix(): void
    {
        update_option(RoutesPage::OPTION_NAME, [
            [
                'id' => 'route_1',
                'enabled' => true,
                'path' => '/api/*',
                'target' => 'https://api.example.com',
                'methods' => ['GET', 'POST'],
                'middlewares' => [],
            ],
        ]);

        $loader = new WordPressLoader();
        $routes = $loader->load();

        $this->assertCount(1, $routes);
        $this->assertEquals('GET|POST /api/*', $routes[0]['path']);
    }

    public function test_load_preserves_middlewares(): void
    {
        update_option(RoutesPage::OPTION_NAME, [
            [
                'id' => 'route_1',
                'enabled' => true,
                'path' => '/api/*',
                'target' => 'https://api.example.com',
                'methods' => [],
                'middlewares' => [
                    'ProxyHeaders',
                    ['SetHost', 'api.example.com'],
                ],
            ],
        ]);

        $loader = new WordPressLoader();
        $routes = $loader->load();

        $this->assertCount(1, $routes);
        $this->assertCount(2, $routes[0]['middlewares']);
        $this->assertEquals('ProxyHeaders', $routes[0]['middlewares'][0]);
        $this->assertEquals(['SetHost', 'api.example.com'], $routes[0]['middlewares'][1]);
    }

    public function test_get_cache_key_returns_consistent_key(): void
    {
        $loader1 = new WordPressLoader();
        $loader2 = new WordPressLoader();

        $this->assertNotNull($loader1->getCacheKey());
        $this->assertEquals($loader1->getCacheKey(), $loader2->getCacheKey());
    }

    public function test_get_cache_metadata_returns_version(): void
    {
        $loader = new WordPressLoader();

        // Initial version is 0
        $this->assertEquals(0, $loader->getCacheMetadata());

        // After saving a route, version increments
        $routesPage = new RoutesPage();
        $routesPage->saveRoute([
            'path' => '/api/*',
            'target' => 'https://api.example.com',
            'enabled' => true,
        ]);

        $this->assertEquals(1, $loader->getCacheMetadata());
    }

    public function test_cache_metadata_changes_when_route_saved(): void
    {
        $routesPage = new RoutesPage();
        $loader = new WordPressLoader();

        // Save first route
        $routesPage->saveRoute([
            'path' => '/api/*',
            'target' => 'https://api.example.com',
            'enabled' => true,
        ]);
        $metadata1 = $loader->getCacheMetadata();

        // Save second route
        $routesPage->saveRoute([
            'path' => '/web/*',
            'target' => 'https://web.example.com',
            'enabled' => true,
        ]);
        $metadata2 = $loader->getCacheMetadata();

        $this->assertNotEquals($metadata1, $metadata2);
        $this->assertEquals(1, $metadata1);
        $this->assertEquals(2, $metadata2);
    }

    public function test_is_cache_valid_returns_true_for_same_metadata(): void
    {
        $routesPage = new RoutesPage();
        $routesPage->saveRoute([
            'path' => '/api/*',
            'target' => 'https://api.example.com',
            'enabled' => true,
        ]);

        $loader = new WordPressLoader();
        $metadata = $loader->getCacheMetadata();

        $this->assertTrue($loader->isCacheValid($metadata));
    }

    public function test_is_cache_valid_returns_false_when_route_changed(): void
    {
        $routesPage = new RoutesPage();
        $routesPage->saveRoute([
            'path' => '/api/*',
            'target' => 'https://api.example.com',
            'enabled' => true,
        ]);

        $loader = new WordPressLoader();
        $oldMetadata = $loader->getCacheMetadata();

        // Save another route (increments version)
        $routesPage->saveRoute([
            'path' => '/web/*',
            'target' => 'https://web.example.com',
            'enabled' => true,
        ]);

        $this->assertFalse($loader->isCacheValid($oldMetadata));
    }

    public function test_version_increments_on_delete(): void
    {
        $routesPage = new RoutesPage();
        $routesPage->saveRoute([
            'id' => 'route_1',
            'path' => '/api/*',
            'target' => 'https://api.example.com',
            'enabled' => true,
        ]);

        $loader = new WordPressLoader();
        $versionAfterSave = $loader->getCacheMetadata();

        $routesPage->deleteRoute('route_1');
        $versionAfterDelete = $loader->getCacheMetadata();

        $this->assertEquals($versionAfterSave + 1, $versionAfterDelete);
    }

    public function test_version_increments_on_toggle(): void
    {
        $routesPage = new RoutesPage();
        $routesPage->saveRoute([
            'id' => 'route_1',
            'path' => '/api/*',
            'target' => 'https://api.example.com',
            'enabled' => true,
        ]);

        $loader = new WordPressLoader();
        $versionAfterSave = $loader->getCacheMetadata();

        $routesPage->toggleRoute('route_1');
        $versionAfterToggle = $loader->getCacheMetadata();

        $this->assertEquals($versionAfterSave + 1, $versionAfterToggle);
    }

    public function test_works_with_route_collection(): void
    {
        update_option(RoutesPage::OPTION_NAME, [
            [
                'id' => 'route_1',
                'enabled' => true,
                'path' => '/api/*',
                'target' => 'https://api.example.com',
                'methods' => [],
                'middlewares' => [],
            ],
        ]);

        $loader = new WordPressLoader();
        $collection = new \Recca0120\ReverseProxy\Routing\RouteCollection([$loader]);
        $collection->load();

        $this->assertCount(1, $collection);
        $this->assertEquals('api.example.com', $collection[0]->getTargetHost());
    }

    /**
     * @dataProvider middlewareWithParametersProvider
     * @param string|array $middlewareConfig
     */
    public function test_middleware_with_parameters_can_be_instantiated(
        $middlewareConfig,
        string $expectedClass
    ): void {
        update_option(RoutesPage::OPTION_NAME, [
            [
                'id' => 'route_1',
                'enabled' => true,
                'path' => '/api/*',
                'target' => 'https://api.example.com',
                'methods' => [],
                'middlewares' => [$middlewareConfig],
            ],
        ]);

        $loader = new WordPressLoader();
        $manager = new \Recca0120\ReverseProxy\Routing\MiddlewareManager();
        $collection = new \Recca0120\ReverseProxy\Routing\RouteCollection([$loader], $manager);
        $collection->load();

        $route = $collection[0];
        $middlewares = $route->getMiddlewares();

        $this->assertCount(1, $middlewares);
        $this->assertInstanceOf($expectedClass, $middlewares[0]);
    }

    public static function middlewareWithParametersProvider(): array
    {
        return [
            'SetHost with host parameter' => [
                ['SetHost', 'api.example.com'],
                \Recca0120\ReverseProxy\Middleware\SetHost::class,
            ],
            'Timeout with seconds parameter' => [
                ['Timeout', 60],
                \Recca0120\ReverseProxy\Middleware\Timeout::class,
            ],
            'Caching with ttl parameter' => [
                ['Caching', 600],
                \Recca0120\ReverseProxy\Middleware\Caching::class,
            ],
            'RequestId with headerName parameter' => [
                ['RequestId', 'X-Correlation-ID'],
                \Recca0120\ReverseProxy\Middleware\RequestId::class,
            ],
            'RewritePath with replacement parameter' => [
                ['RewritePath', '/v2$1'],
                \Recca0120\ReverseProxy\Middleware\RewritePath::class,
            ],
            'RateLimiting with maxRequests and windowSeconds' => [
                ['RateLimiting', 100, 60],
                \Recca0120\ReverseProxy\Middleware\RateLimiting::class,
            ],
            'CircuitBreaker with all parameters' => [
                ['CircuitBreaker', 'api-service', 5, 60, [500, 502, 503]],
                \Recca0120\ReverseProxy\Middleware\CircuitBreaker::class,
            ],
            'Retry with all parameters' => [
                ['Retry', 3, ['GET', 'HEAD'], [502, 503, 504]],
                \Recca0120\ReverseProxy\Middleware\Retry::class,
            ],
            'Cors with all parameters' => [
                ['Cors', ['https://example.com'], ['GET', 'POST'], ['Content-Type'], true, 3600],
                \Recca0120\ReverseProxy\Middleware\Cors::class,
            ],
            'IpFilter with mode and ips array' => [
                ['IpFilter', 'allow', ['192.168.1.0/24', '10.0.0.0/8']],
                \Recca0120\ReverseProxy\Middleware\IpFilter::class,
            ],
            'AllowMethods with methods array' => [
                ['AllowMethods', ['GET', 'POST', 'PUT']],
                \Recca0120\ReverseProxy\Middleware\AllowMethods::class,
            ],
            'Fallback with statusCodes array' => [
                ['Fallback', [404, 500, 502]],
                \Recca0120\ReverseProxy\Middleware\Fallback::class,
            ],
            'RewriteBody with replacements' => [
                ['RewriteBody', ['http://old.com' => 'https://new.com']],
                \Recca0120\ReverseProxy\Middleware\RewriteBody::class,
            ],
            'ProxyHeaders without parameters' => [
                'ProxyHeaders',
                \Recca0120\ReverseProxy\Middleware\ProxyHeaders::class,
            ],
        ];
    }

    public function test_cors_middleware_parameters_are_applied_correctly(): void
    {
        update_option(RoutesPage::OPTION_NAME, [
            [
                'id' => 'route_1',
                'enabled' => true,
                'path' => '/api/*',
                'target' => 'https://api.example.com',
                'methods' => [],
                'middlewares' => [
                    ['Cors', ['https://allowed.com'], ['GET', 'POST'], ['Authorization'], true, 7200],
                ],
            ],
        ]);

        $loader = new WordPressLoader();
        $manager = new \Recca0120\ReverseProxy\Routing\MiddlewareManager();
        $collection = new \Recca0120\ReverseProxy\Routing\RouteCollection([$loader], $manager);
        $collection->load();

        $route = $collection[0];
        $cors = $route->getMiddlewares()[0];

        // Test CORS middleware by processing a preflight request
        $request = (new \Nyholm\Psr7\ServerRequest('OPTIONS', 'https://example.com/api/test'))
            ->withHeader('Origin', 'https://allowed.com')
            ->withHeader('Access-Control-Request-Method', 'POST');

        $response = $cors->process($request, function () {
            return new \Nyholm\Psr7\Response(200);
        });

        $this->assertEquals('https://allowed.com', $response->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertStringContainsString('GET', $response->getHeaderLine('Access-Control-Allow-Methods'));
        $this->assertStringContainsString('POST', $response->getHeaderLine('Access-Control-Allow-Methods'));
        $this->assertStringContainsString('Authorization', $response->getHeaderLine('Access-Control-Allow-Headers'));
        $this->assertEquals('true', $response->getHeaderLine('Access-Control-Allow-Credentials'));
        $this->assertEquals('7200', $response->getHeaderLine('Access-Control-Max-Age'));
    }

    public function test_timeout_middleware_parameter_is_applied(): void
    {
        update_option(RoutesPage::OPTION_NAME, [
            [
                'id' => 'route_1',
                'enabled' => true,
                'path' => '/api/*',
                'target' => 'https://api.example.com',
                'methods' => [],
                'middlewares' => [
                    ['Timeout', 120],
                ],
            ],
        ]);

        $loader = new WordPressLoader();
        $manager = new \Recca0120\ReverseProxy\Routing\MiddlewareManager();
        $collection = new \Recca0120\ReverseProxy\Routing\RouteCollection([$loader], $manager);
        $collection->load();

        $route = $collection[0];
        $timeout = $route->getMiddlewares()[0];

        $request = new \Nyholm\Psr7\ServerRequest('GET', 'https://example.com/api/test');

        // Process and verify the timeout header is set
        $capturedRequest = null;
        $timeout->process($request, function ($req) use (&$capturedRequest) {
            $capturedRequest = $req;
            return new \Nyholm\Psr7\Response(200);
        });

        $this->assertEquals('120', $capturedRequest->getHeaderLine('X-Timeout'));
    }

    public function test_set_host_middleware_parameter_is_applied(): void
    {
        update_option(RoutesPage::OPTION_NAME, [
            [
                'id' => 'route_1',
                'enabled' => true,
                'path' => '/api/*',
                'target' => 'https://api.example.com',
                'methods' => [],
                'middlewares' => [
                    ['SetHost', 'custom-host.example.com'],
                ],
            ],
        ]);

        $loader = new WordPressLoader();
        $manager = new \Recca0120\ReverseProxy\Routing\MiddlewareManager();
        $collection = new \Recca0120\ReverseProxy\Routing\RouteCollection([$loader], $manager);
        $collection->load();

        $route = $collection[0];
        $setHost = $route->getMiddlewares()[0];

        $request = new \Nyholm\Psr7\ServerRequest('GET', 'https://example.com/api/test');

        $capturedRequest = null;
        $setHost->process($request, function ($req) use (&$capturedRequest) {
            $capturedRequest = $req;
            return new \Nyholm\Psr7\Response(200);
        });

        $this->assertEquals('custom-host.example.com', $capturedRequest->getHeaderLine('Host'));
    }

    public function test_ip_filter_middleware_parameters_are_applied(): void
    {
        update_option(RoutesPage::OPTION_NAME, [
            [
                'id' => 'route_1',
                'enabled' => true,
                'path' => '/api/*',
                'target' => 'https://api.example.com',
                'methods' => [],
                'middlewares' => [
                    ['IpFilter', 'allow', ['192.168.1.0/24']],
                ],
            ],
        ]);

        $loader = new WordPressLoader();
        $manager = new \Recca0120\ReverseProxy\Routing\MiddlewareManager();
        $collection = new \Recca0120\ReverseProxy\Routing\RouteCollection([$loader], $manager);
        $collection->load();

        $route = $collection[0];
        $ipFilter = $route->getMiddlewares()[0];

        // Test allowed IP
        $allowedRequest = (new \Nyholm\Psr7\ServerRequest('GET', 'https://example.com/api/test'))
            ->withAttribute('server_params', ['REMOTE_ADDR' => '192.168.1.100']);
        // Simulate server params
        $allowedRequest = new \Nyholm\Psr7\ServerRequest(
            'GET',
            'https://example.com/api/test',
            [],
            null,
            '1.1',
            ['REMOTE_ADDR' => '192.168.1.100']
        );

        $response = $ipFilter->process($allowedRequest, function () {
            return new \Nyholm\Psr7\Response(200, [], 'OK');
        });

        $this->assertEquals(200, $response->getStatusCode());

        // Test denied IP
        $deniedRequest = new \Nyholm\Psr7\ServerRequest(
            'GET',
            'https://example.com/api/test',
            [],
            null,
            '1.1',
            ['REMOTE_ADDR' => '10.0.0.1']
        );

        $response = $ipFilter->process($deniedRequest, function () {
            return new \Nyholm\Psr7\Response(200, [], 'OK');
        });

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_allow_methods_middleware_parameters_are_applied(): void
    {
        update_option(RoutesPage::OPTION_NAME, [
            [
                'id' => 'route_1',
                'enabled' => true,
                'path' => '/api/*',
                'target' => 'https://api.example.com',
                'methods' => [],
                'middlewares' => [
                    ['AllowMethods', ['GET', 'POST']],
                ],
            ],
        ]);

        $loader = new WordPressLoader();
        $manager = new \Recca0120\ReverseProxy\Routing\MiddlewareManager();
        $collection = new \Recca0120\ReverseProxy\Routing\RouteCollection([$loader], $manager);
        $collection->load();

        $route = $collection[0];
        $allowMethods = $route->getMiddlewares()[0];

        // Test allowed method
        $getRequest = new \Nyholm\Psr7\ServerRequest('GET', 'https://example.com/api/test');
        $response = $allowMethods->process($getRequest, function () {
            return new \Nyholm\Psr7\Response(200);
        });
        $this->assertEquals(200, $response->getStatusCode());

        // Test denied method
        $deleteRequest = new \Nyholm\Psr7\ServerRequest('DELETE', 'https://example.com/api/test');
        $response = $allowMethods->process($deleteRequest, function () {
            return new \Nyholm\Psr7\Response(200);
        });
        $this->assertEquals(405, $response->getStatusCode());
    }

    public function test_request_id_middleware_parameter_is_applied(): void
    {
        update_option(RoutesPage::OPTION_NAME, [
            [
                'id' => 'route_1',
                'enabled' => true,
                'path' => '/api/*',
                'target' => 'https://api.example.com',
                'methods' => [],
                'middlewares' => [
                    ['RequestId', 'X-Trace-ID'],
                ],
            ],
        ]);

        $loader = new WordPressLoader();
        $manager = new \Recca0120\ReverseProxy\Routing\MiddlewareManager();
        $collection = new \Recca0120\ReverseProxy\Routing\RouteCollection([$loader], $manager);
        $collection->load();

        $route = $collection[0];
        $requestId = $route->getMiddlewares()[0];

        $request = new \Nyholm\Psr7\ServerRequest('GET', 'https://example.com/api/test');

        $capturedRequest = null;
        $response = $requestId->process($request, function ($req) use (&$capturedRequest) {
            $capturedRequest = $req;
            return new \Nyholm\Psr7\Response(200);
        });

        // Verify the custom header name is used
        $this->assertTrue($capturedRequest->hasHeader('X-Trace-ID'));
        $this->assertTrue($response->hasHeader('X-Trace-ID'));
    }

    public function test_multiple_middlewares_with_parameters(): void
    {
        update_option(RoutesPage::OPTION_NAME, [
            [
                'id' => 'route_1',
                'enabled' => true,
                'path' => '/api/*',
                'target' => 'https://api.example.com',
                'methods' => [],
                'middlewares' => [
                    'ProxyHeaders',
                    ['SetHost', 'backend.example.com'],
                    ['Timeout', 30],
                    ['RequestId', 'X-Request-ID'],
                ],
            ],
        ]);

        $loader = new WordPressLoader();
        $manager = new \Recca0120\ReverseProxy\Routing\MiddlewareManager();
        $collection = new \Recca0120\ReverseProxy\Routing\RouteCollection([$loader], $manager);
        $collection->load();

        $route = $collection[0];
        $middlewares = $route->getMiddlewares();

        $this->assertCount(4, $middlewares);

        // Check all expected middleware types are present (order may vary by priority)
        $middlewareClasses = array_map('get_class', $middlewares);
        $this->assertContains(\Recca0120\ReverseProxy\Middleware\ProxyHeaders::class, $middlewareClasses);
        $this->assertContains(\Recca0120\ReverseProxy\Middleware\SetHost::class, $middlewareClasses);
        $this->assertContains(\Recca0120\ReverseProxy\Middleware\Timeout::class, $middlewareClasses);
        $this->assertContains(\Recca0120\ReverseProxy\Middleware\RequestId::class, $middlewareClasses);
    }
}
