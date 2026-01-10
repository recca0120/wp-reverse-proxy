<?php

namespace Recca0120\ReverseProxy\Tests\Integration\WordPress\Admin;

use Recca0120\ReverseProxy\WordPress\Admin\OptionsStorage;
use WP_UnitTestCase;

class OptionsStorageTest extends WP_UnitTestCase
{
    private const OPTION_NAME = 'reverse_proxy_admin_routes';

    private const VERSION_OPTION_NAME = 'reverse_proxy_admin_routes_version';

    /** @var OptionsStorage */
    private $storage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storage = new OptionsStorage();
    }

    protected function tearDown(): void
    {
        delete_option(self::OPTION_NAME);
        delete_option(self::VERSION_OPTION_NAME);
        parent::tearDown();
    }

    public function test_get_all_returns_empty_array_when_no_routes()
    {
        $routes = $this->storage->getAll();

        $this->assertIsArray($routes);
        $this->assertEmpty($routes);
    }

    public function test_save_stores_routes_in_wp_options()
    {
        $routes = [
            [
                'id' => 'route_1',
                'path' => '/api/*',
                'target' => 'https://api.example.com',
                'methods' => ['GET', 'POST'],
                'middlewares' => [],
                'enabled' => true,
            ],
        ];

        $result = $this->storage->save($routes);

        $this->assertTrue($result);
        $this->assertEquals($routes, get_option(self::OPTION_NAME));
    }

    public function test_get_all_returns_saved_routes()
    {
        $routes = [
            [
                'id' => 'route_1',
                'path' => '/api/*',
                'target' => 'https://api.example.com',
                'methods' => ['GET'],
                'middlewares' => [],
                'enabled' => true,
            ],
            [
                'id' => 'route_2',
                'path' => '/static/*',
                'target' => 'https://static.example.com',
                'methods' => ['GET'],
                'middlewares' => [],
                'enabled' => false,
            ],
        ];

        $this->storage->save($routes);
        $retrieved = $this->storage->getAll();

        $this->assertEquals($routes, $retrieved);
    }

    public function test_get_version_returns_zero_initially()
    {
        $version = $this->storage->getVersion();

        $this->assertEquals(0, $version);
    }

    public function test_save_increments_version()
    {
        $initialVersion = $this->storage->getVersion();

        $this->storage->save([['id' => 'route_1', 'path' => '/test/*', 'target' => 'https://test.com', 'methods' => [], 'middlewares' => [], 'enabled' => true]]);

        $newVersion = $this->storage->getVersion();

        $this->assertEquals($initialVersion + 1, $newVersion);
    }

    public function test_multiple_saves_increment_version_each_time()
    {
        $this->storage->save([]);
        $version1 = $this->storage->getVersion();

        $this->storage->save([['id' => 'route_1', 'path' => '/a/*', 'target' => 'https://a.com', 'methods' => [], 'middlewares' => [], 'enabled' => true]]);
        $version2 = $this->storage->getVersion();

        $this->storage->save([['id' => 'route_2', 'path' => '/b/*', 'target' => 'https://b.com', 'methods' => [], 'middlewares' => [], 'enabled' => true]]);
        $version3 = $this->storage->getVersion();

        $this->assertEquals(1, $version1);
        $this->assertEquals(2, $version2);
        $this->assertEquals(3, $version3);
    }

    public function test_save_overwrites_existing_routes()
    {
        $routes1 = [['id' => 'route_1', 'path' => '/old/*', 'target' => 'https://old.com', 'methods' => [], 'middlewares' => [], 'enabled' => true]];
        $routes2 = [['id' => 'route_2', 'path' => '/new/*', 'target' => 'https://new.com', 'methods' => [], 'middlewares' => [], 'enabled' => true]];

        $this->storage->save($routes1);
        $this->storage->save($routes2);

        $retrieved = $this->storage->getAll();

        $this->assertEquals($routes2, $retrieved);
        $this->assertCount(1, $retrieved);
    }
}
