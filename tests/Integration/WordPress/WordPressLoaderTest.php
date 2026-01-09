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
    }

    protected function tearDown(): void
    {
        delete_option(RoutesPage::OPTION_NAME);
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

    public function test_get_cache_metadata_returns_hash_of_option(): void
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
        $metadata = $loader->getCacheMetadata();

        $this->assertIsString($metadata);
        $this->assertNotEmpty($metadata);
    }

    public function test_cache_metadata_changes_when_option_changes(): void
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
        $metadata1 = $loader->getCacheMetadata();

        // Update option
        update_option(RoutesPage::OPTION_NAME, [
            [
                'id' => 'route_1',
                'enabled' => true,
                'path' => '/api/v2/*',
                'target' => 'https://api.example.com',
                'methods' => [],
                'middlewares' => [],
            ],
        ]);

        $metadata2 = $loader->getCacheMetadata();

        $this->assertNotEquals($metadata1, $metadata2);
    }

    public function test_is_cache_valid_returns_true_for_same_metadata(): void
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
        $metadata = $loader->getCacheMetadata();

        $this->assertTrue($loader->isCacheValid($metadata));
    }

    public function test_is_cache_valid_returns_false_when_option_changed(): void
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
        $oldMetadata = $loader->getCacheMetadata();

        // Update option
        update_option(RoutesPage::OPTION_NAME, [
            [
                'id' => 'route_2',
                'enabled' => true,
                'path' => '/web/*',
                'target' => 'https://web.example.com',
                'methods' => [],
                'middlewares' => [],
            ],
        ]);

        $this->assertFalse($loader->isCacheValid($oldMetadata));
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
}
